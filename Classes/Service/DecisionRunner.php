<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Client\JevClientInterface;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Exception\InvalidQuestionException;
use Webconsulting\WebconJev\Exception\JevException;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Runs a decision: builds the state, reuses a cached answer where it can, keeps the call inside
 * the budget, and falls back instead of failing.
 *
 * Nothing here ever throws at the integration. A form must not break because a model is having a
 * bad minute, so every failure becomes a fallback result the caller can still act on.
 *
 * The decision may be one an integration built in code for the occasion — uid 0, questions it only
 * knows at run time. It goes through the same switch, token check, cache, budget guard, fallback
 * and run log as a stored one.
 */
final readonly class DecisionRunner
{
    /** Cache key prefix of the per-minute call counter the budget guard keeps. */
    private const string RATE_BUCKET_PREFIX = 'rate_';

    public function __construct(
        private JevClientInterface $client,
        private StateBuilder $stateBuilder,
        private RunLogger $runLogger,
        private Settings $settings,
        private FrontendInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $context What the integration knows — form values, page, language
     * @param string               $runContext Where this call came from, for the run log
     */
    public function run(
        Decision $decision,
        array $context,
        string $runContext = '',
        string $origin = '',
    ): DecisionOutcome {
        // A question can be stored in a shape the API refuses — a choice left with one option after
        // an edit in the record editor, say. That is a reason to fall back, not to break the form.
        try {
            $questions = $decision->toClientQuestions();
        } catch (InvalidQuestionException $exception) {
            return $this->fallback(
                $decision,
                'the decision cannot be asked as it stands: ' . $exception->getMessage(),
                $runContext,
                $origin,
                '',
            );
        }
        if ($questions === []) {
            return $this->fallback($decision, 'the decision has no questions', $runContext, $origin, '');
        }

        if (!$this->settings->isEnabled()) {
            return $this->fallback($decision, 'Jev is switched off in the extension configuration', $runContext, $origin, '');
        }

        if (!$this->client->isConfigured()) {
            return $this->fallback($decision, 'no API token is configured', $runContext, $origin, '');
        }

        $state = $this->stateBuilder->build($decision, $context);
        $stateHash = $this->hash($decision, $questions, $state);

        $cached = $this->readCache($decision, $stateHash);
        if ($cached !== null) {
            $outcome = new DecisionOutcome($decision, $cached);
            $this->runLogger->log($decision, $cached, $runContext, $origin, $stateHash);

            return $outcome;
        }

        if (!$this->withinBudget()) {
            return $this->fallback(
                $decision,
                sprintf('the budget guard of %d calls a minute is spent', $this->settings->maxCallsPerMinute()),
                $runContext,
                $origin,
                $stateHash,
            );
        }

        try {
            $result = $this->client->ask($state, $questions, $decision->model !== '' ? $decision->model : null);
        } catch (JevException $exception) {
            $this->logger->warning('A Jev decision fell back.', [
                'decision' => $decision->identifier,
                'exception' => $exception->getMessage(),
            ]);

            return $this->fallback($decision, $exception->getMessage(), $runContext, $origin, $stateHash);
        }

        $this->writeCache($decision, $stateHash, $result);
        $this->runLogger->log($decision, $result, $runContext, $origin, $stateHash);

        return new DecisionOutcome($decision, $result);
    }

    private function fallback(
        Decision $decision,
        string $reason,
        string $runContext,
        string $origin,
        string $stateHash,
    ): DecisionOutcome {
        $result = DecisionResult::fallback($reason, $decision->model);
        $this->runLogger->log($decision, $result, $runContext, $origin, $stateHash);

        return new DecisionOutcome($decision, $result);
    }

    /**
     * @param array<string, Question>     $questions
     * @param string|array<string, mixed> $state
     */
    private function hash(Decision $decision, array $questions, string|array $state): string
    {
        // Everything the answer depends on. The model is part of it: an answer one model gave is not
        // the answer to ask another for, and nothing flushes the cache when a decision's model changes.
        return hash('xxh128', json_encode([
            'decision' => $decision->uid,
            'language' => $decision->languageId,
            'model' => $decision->model,
            'questions' => array_map(static fn(Question $question): array => $question->toPayload(), $questions),
            'state' => $state,
        ], JSON_THROW_ON_ERROR));
    }

    private function lifetime(Decision $decision): int
    {
        return $decision->cacheLifetime === Decision::CACHE_LIFETIME_INHERIT
            ? $this->settings->cacheLifetime()
            : $decision->cacheLifetime;
    }

    private function readCache(Decision $decision, string $stateHash): ?DecisionResult
    {
        if ($this->lifetime($decision) <= 0) {
            return null;
        }

        $cached = Cast::map($this->cache->get($stateHash));
        if ($cached === []) {
            return null;
        }

        return $this->restore($cached)?->withCacheFlag(true);
    }

    private function writeCache(Decision $decision, string $stateHash, DecisionResult $result): void
    {
        $lifetime = $this->lifetime($decision);
        if ($lifetime <= 0 || $result->isFallback) {
            return;
        }

        $this->cache->set(
            $stateHash,
            $result->toArray(),
            ['jev_decision_' . $decision->uid],
            $lifetime,
        );
    }

    /**
     * @param array<string, mixed> $cached
     */
    private function restore(array $cached): ?DecisionResult
    {
        if (!is_array($cached['answers'] ?? null)) {
            return null;
        }

        $answers = [];
        foreach (Cast::map($cached['answers']) as $name => $rawAnswer) {
            $answer = Cast::map($rawAnswer);
            $type = QuestionType::tryFrom(Cast::string($answer['type'] ?? null));
            if ($answer === [] || $type === null) {
                continue;
            }
            $answers[$name] = Answer::fromResponse($name, $type, [
                $type->answerKey() => $answer['value'] ?? null,
                'confidence' => $answer['confidence'] ?? 0.0,
                'probabilities' => $answer['probabilities'] ?? [],
                'legend' => $answer['legend'] ?? [],
            ]);
        }

        $usage = Cast::map($cached['usage'] ?? null);

        return new DecisionResult(
            answers: $answers,
            model: Cast::string($cached['model'] ?? null),
            usage: new Usage(Cast::int($usage['inputTokens'] ?? null), Cast::int($usage['outputTokens'] ?? null)),
            durationMs: Cast::float($cached['durationMs'] ?? null),
            fromCache: true,
        );
    }

    /**
     * A crude per-minute counter. It is not exact under concurrency, and does not need to be — it
     * exists so a runaway form cannot spend a month's budget in an afternoon.
     */
    private function withinBudget(): bool
    {
        $limit = $this->settings->maxCallsPerMinute();
        if ($limit <= 0) {
            return true;
        }

        $bucket = self::RATE_BUCKET_PREFIX . (int)floor(time() / 60);
        $used = Cast::int($this->cache->get($bucket));
        if ($used >= $limit) {
            return false;
        }

        $this->cache->set($bucket, $used + 1, ['jev_rate'], 120);

        return true;
    }
}
