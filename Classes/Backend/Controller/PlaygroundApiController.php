<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Backend\RequestPayload;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Editing\DecisionValidator;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Editing\Dto\ValidationError;
use Webconsulting\WebconJev\Service\DecisionOutcome;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\StateBuilder;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Runs a decision against a state somebody typed, and shows what came back.
 *
 * It runs what the editor holds right now, saved or not: trying a new wording must not mean
 * putting it in front of every visitor first. A stored decision can still be run by uid.
 *
 * Caching is bypassed on purpose: a playground that answers from cache cannot tell you whether
 * your edit to the wording changed anything.
 */
#[AsController]
final readonly class PlaygroundApiController
{
    public function __construct(
        private DecisionRepository $decisions,
        private DecisionValidator $validator,
        private DecisionRunner $runner,
        private StateBuilder $stateBuilder,
        private Labels $labels,
    ) {}

    public function runAction(ServerRequestInterface $request): ResponseInterface
    {
        $payload = RequestPayload::fromRequest($request);
        if ($payload === []) {
            return $this->failure($this->labels->get('api.error.noPayload'), 400);
        }

        if (is_array($payload['draft'] ?? null)) {
            $draft = DecisionDraft::fromPayload(Cast::map($payload['draft']));
            $errors = $this->validator->validateForTrying($draft);
            if ($errors !== []) {
                return new JsonResponse([
                    'ok' => false,
                    'message' => $this->labels->get('api.playground.invalid', ['count' => count($errors)]),
                    'errors' => array_map(fn(ValidationError $error): array => [
                        'path' => $error->path,
                        'message' => $this->labels->get($error->labelKey, $error->arguments),
                    ], $errors),
                ], 422);
            }
            $decision = $draft->toDecision();
        } else {
            $decision = $this->decisions->findByUid(
                Cast::int($payload['decision'] ?? null),
                Cast::int($payload['language'] ?? null),
                true,
            );
            if ($decision === null) {
                return $this->failure($this->labels->get('api.playground.notFound'), 404);
            }
        }

        // A lifetime of 0 makes the runner skip the cache in both directions.
        $decision = $decision->withCacheLifetime(0);
        $context = $this->context($payload);
        $outcome = $this->runner->run($decision, $context, RunLogger::CONTEXT_PLAYGROUND, 'backend module');

        return new JsonResponse([
            'ok' => !$outcome->isFallback(),
            'state' => $this->stateBuilder->build($decision, $context),
            'result' => $outcome->result->toArray(),
            'summary' => $outcome->summary(),
            'threshold' => $decision->confidenceThreshold,
            'needsHumanReview' => $outcome->needsHumanReview(),
            'outcomes' => $this->outcomes($outcome),
        ]);
    }

    /**
     * Accept either a ready-made context object or a plain block of text, which becomes one field.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function context(array $payload): array
    {
        $context = Cast::map($payload['context'] ?? null);
        if ($context !== []) {
            return $context;
        }

        return ['field' => ['message' => Cast::string($payload['state'] ?? null)]];
    }

    /**
     * What each choice question would route to, so the playground answers the question an editor
     * actually has: not "what did it say" but "where would this have gone".
     *
     * @return array<string, array{outcome: string, isDefault: bool}>
     */
    private function outcomes(DecisionOutcome $outcome): array
    {
        $outcomes = [];
        foreach ($this->choiceQuestions($outcome->decision) as $name) {
            $value = $outcome->outcomeFor($name);
            if ($value === '') {
                continue;
            }
            // The default stands in when no answer was confident enough, or the option has no outcome.
            $choice = $outcome->confidentAnswer($name)?->choice;
            $optionOutcome = $choice !== null ? $outcome->decision->question($name)?->outcomeValueFor($choice) : null;
            $outcomes[$name] = ['outcome' => $value, 'isDefault' => $optionOutcome === null];
        }

        return $outcomes;
    }

    /**
     * @return list<string>
     */
    private function choiceQuestions(Decision $decision): array
    {
        $names = [];
        foreach ($decision->questions as $question) {
            if ($question->name !== '' && $question->type === QuestionType::Choice) {
                $names[] = $question->name;
            }
        }

        return $names;
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['ok' => false, 'message' => $message], $status);
    }
}
