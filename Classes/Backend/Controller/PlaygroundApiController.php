<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\StateBuilder;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Runs a saved decision against a state somebody typed, and shows what came back.
 *
 * Caching is bypassed on purpose: a playground that answers from cache cannot tell you whether
 * your edit to the wording changed anything.
 */
final readonly class PlaygroundApiController
{
    public function __construct(
        private DecisionRepository $decisions,
        private DecisionRunner $runner,
        private StateBuilder $stateBuilder,
    ) {}

    public function run(ServerRequestInterface $request): ResponseInterface
    {
        $payload = Cast::map(json_decode((string)$request->getBody(), true));
        if ($payload === []) {
            return new JsonResponse(['ok' => false, 'error' => 'Expected a JSON body.'], 400);
        }

        $decision = $this->decisions->findByUid(
            Cast::int($payload['decision'] ?? null),
            Cast::int($payload['language'] ?? null),
            true,
        );
        if ($decision === null) {
            return new JsonResponse(['ok' => false, 'error' => 'No such decision.'], 404);
        }

        $context = $this->context($payload);

        // A lifetime of 0 makes the runner skip the cache in both directions.
        $uncached = new \Webconsulting\WebconJev\Domain\Model\Decision(
            uid: $decision->uid,
            identifier: $decision->identifier,
            title: $decision->title,
            description: $decision->description,
            stateTemplate: $decision->stateTemplate,
            model: $decision->model,
            confidenceThreshold: $decision->confidenceThreshold,
            cacheLifetime: 0,
            defaultOutcome: $decision->defaultOutcome,
            questions: $decision->questions,
            languageId: $decision->languageId,
        );

        $outcome = $this->runner->run($uncached, $context, RunLogger::CONTEXT_PLAYGROUND, 'backend module');

        return new JsonResponse([
            'ok' => !$outcome->isFallback(),
            'state' => $this->stateBuilder->build($uncached, $context),
            'result' => $outcome->result->toArray(),
            'summary' => $outcome->summary(),
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
     * @return array<string, string>
     */
    private function outcomes(\Webconsulting\WebconJev\Service\DecisionOutcome $outcome): array
    {
        $outcomes = [];
        foreach ($outcome->decision->questions as $question) {
            $value = $outcome->outcomeFor($question->name);
            if ($value !== '') {
                $outcomes[$question->name] = $value;
            }
        }

        return $outcomes;
    }
}
