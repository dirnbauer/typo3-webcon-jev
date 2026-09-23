<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\JsonResponse;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Backend\RequestPayload;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Editing\DecisionValidator;
use Webconsulting\WebconJev\Editing\DecisionWriter;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Editing\Dto\ValidationError;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Saving and deleting decisions from the module's editor.
 *
 * Every answer carries a message in the backend user's language, and a refused save names the
 * field each problem belongs to, so the editor can put it next to the input.
 */
#[AsController]
final readonly class DecisionApiController
{
    public function __construct(
        private DecisionRepository $decisions,
        private DecisionValidator $validator,
        private DecisionWriter $writer,
        private UriBuilder $uriBuilder,
        private Labels $labels,
    ) {}

    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $payload = RequestPayload::fromRequest($request);
        if (!is_array($payload['decision'] ?? null)) {
            return $this->failure($this->labels->get('api.error.noDecision'), 400);
        }

        $draft = DecisionDraft::fromPayload(Cast::map($payload['decision']));
        $errors = $this->validator->validate($draft);
        if ($errors !== []) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->labels->get('api.save.invalid', ['count' => count($errors)]),
                'errors' => $this->translate($errors),
            ], 422);
        }

        $result = $this->writer->save($draft);
        $saved = $result['uid'] > 0 ? $this->decisions->findByUid($result['uid'], 0, true) : null;
        if ($result['errors'] !== [] || $saved === null) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->labels->get('api.save.refused'),
                'details' => $result['errors'],
            ], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->labels->get('api.save.saved', [$saved->title]),
            'decision' => $saved->toArray(),
            'urls' => [
                'edit' => (string)$this->uriBuilder->buildUriFromRoute(
                    DecisionsController::EDIT_ROUTE,
                    ['decision' => $saved->uid],
                ),
                'runLog' => (string)$this->uriBuilder->buildUriFromRoute(
                    RunLogController::MODULE,
                    ['filter' => ['decision' => $saved->uid]],
                ),
            ],
        ]);
    }

    public function deleteAction(ServerRequestInterface $request): ResponseInterface
    {
        $uid = Cast::int(RequestPayload::fromRequest($request)['uid'] ?? null);
        if ($uid <= 0) {
            return $this->failure($this->labels->get('api.error.noDecision'), 400);
        }

        $decision = $this->decisions->findByUid($uid, 0, true);
        if ($decision === null) {
            return $this->failure($this->labels->get('api.delete.notFound', [$uid]), 404);
        }

        // A translation's uid stands for its decision (see DecisionRepository::findByUid()), and
        // deleting the decision takes its translations with it — never a translation on its own.
        $errors = $this->writer->delete($decision->uid);
        if ($errors !== []) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->labels->get('api.delete.refused'),
                'details' => $errors,
            ], 422);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->labels->get('api.delete.deleted', [$decision->title]),
        ]);
    }

    /**
     * @param list<ValidationError> $errors
     *
     * @return list<array{path: string, message: string}>
     */
    private function translate(array $errors): array
    {
        return array_map(fn(ValidationError $error): array => [
            'path' => $error->path,
            'message' => $this->labels->get($error->labelKey, $error->arguments),
        ], $errors);
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['ok' => false, 'message' => $message], $status);
    }
}
