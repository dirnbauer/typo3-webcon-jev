<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\JevClientInterface;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Exception\JevException;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Connection status, a live ping, and the run log.
 */
final readonly class StatusApiController
{
    public function __construct(
        private JevClientInterface $client,
        private TokenProvider $tokenProvider,
        private RunLogger $runLogger,
        private Settings $settings,
    ) {}

    public function status(ServerRequestInterface $request): ResponseInterface
    {
        $dayAgo = time() - 86400;
        $monthAgo = time() - 30 * 86400;

        return new JsonResponse([
            'connection' => [
                'endpoint' => $this->settings->endpoint(),
                'model' => $this->settings->model(),
                'enabled' => $this->settings->isEnabled(),
                'tokenSource' => $this->tokenProvider->describeSource(),
                'hasToken' => $this->tokenProvider->hasToken(),
                'maxCallsPerMinute' => $this->settings->maxCallsPerMinute(),
                'cacheLifetime' => $this->settings->cacheLifetime(),
            ],
            'today' => $this->runLogger->totalsSince($dayAgo),
            'month' => $this->runLogger->totalsSince($monthAgo),
        ]);
    }

    /**
     * One real call, so "is it working" has an answer that does not depend on a form.
     */
    public function ping(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->tokenProvider->hasToken()) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'No API token. Set TYPESAFE_API_KEY and run "webcon-jev:token:import".',
            ]);
        }

        try {
            $result = $this->client->ask(
                'The delivery arrived three days late and the box was crushed.',
                [
                    'mood' => new Question(
                        name: 'mood',
                        type: QuestionType::Choice,
                        instructions: 'How does the writer feel about what happened?',
                        criteria: [
                            'happy' => 'Pleased with how it went',
                            'annoyed' => 'Unhappy about a problem',
                            'neutral' => 'Reporting without feeling either way',
                        ],
                    ),
                ],
            );
        } catch (JevException $exception) {
            return new JsonResponse(['ok' => false, 'error' => $exception->getMessage()]);
        }

        return new JsonResponse(['ok' => true, 'result' => $result->toArray()]);
    }

    public function runs(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();
        $rows = $this->runLogger->recent(
            Cast::int($query['limit'] ?? null, 50),
            Cast::int($query['decision'] ?? null),
        );

        return new JsonResponse([
            'runs' => array_map(static fn(array $row): array => [
                'uid' => Cast::int($row['uid'] ?? null),
                'crdate' => Cast::int($row['crdate'] ?? null),
                'decision' => Cast::int($row['decision'] ?? null),
                'decisionIdentifier' => Cast::string($row['decision_identifier'] ?? null),
                'context' => Cast::string($row['context'] ?? null),
                'origin' => Cast::string($row['origin'] ?? null),
                'model' => Cast::string($row['model'] ?? null),
                'durationMs' => Cast::float($row['duration_ms'] ?? null),
                'inputTokens' => Cast::int($row['input_tokens'] ?? null),
                'costUsd' => Cast::float($row['cost_usd'] ?? null),
                'fromCache' => Cast::bool($row['from_cache'] ?? null),
                'isFallback' => Cast::bool($row['is_fallback'] ?? null),
                'fallbackReason' => Cast::string($row['fallback_reason'] ?? null),
                'answers' => Cast::map(json_decode(Cast::string($row['answers'] ?? null, '[]'), true)),
            ], $rows),
        ]);
    }
}
