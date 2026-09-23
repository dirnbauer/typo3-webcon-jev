<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Exception\JevException;
use Webconsulting\WebconJev\Service\ConnectionProbe;
use Webconsulting\WebconJev\Service\TokenProvider;

/**
 * The module's "send a test question": one real call, so "is it working" has an answer that does
 * not depend on a form.
 *
 * A failed check is still a successful request — the answer is "no", with the reason — so it comes
 * back with status 200 and "ok": false.
 */
#[AsController]
final readonly class ConnectionApiController
{
    public function __construct(
        private ConnectionProbe $probe,
        private TokenProvider $tokenProvider,
        private Labels $labels,
    ) {}

    public function pingAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->tokenProvider->hasToken()) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->labels->get('connection.ping.noToken'),
            ]);
        }

        try {
            $result = $this->probe->ask();
        } catch (JevException $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->labels->get('connection.ping.failed'),
                'detail' => $exception->getMessage(),
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->labels->get('connection.ping.ok', [(int)round($result->durationMs), $result->model]),
            'result' => $result->toArray(),
        ]);
    }
}
