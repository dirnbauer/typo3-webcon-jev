<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Webconsulting\ShadcnUi\Backend\ShadcnApp;
use Webconsulting\ShadcnUi\Backend\ShadcnModuleRenderer;
use Webconsulting\ShadcnUi\Backend\ShellLayout;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Service\TokenProvider;

/**
 * The Jev module: define decisions, try them against a sample state, and see what they cost.
 *
 * Built on the shadcn/ui backend base, so the AI chat rail is there to ask about a decision that
 * is not behaving.
 */
final readonly class JevModuleController
{
    /**
     * The renderer is nullable with a null default on purpose: that is how Symfony autowiring
     * expresses "use this service if some active extension defines it, otherwise pass null". A
     * required argument here made the whole container fail to compile whenever shadcn_ui was
     * absent or merely inactive — and class_exists() cannot tell those apart in Composer mode,
     * where every installed package is autoloadable. The module itself is only registered when
     * shadcn_ui is loaded (see Configuration/Backend/Modules.php), so index() never runs without
     * the renderer; the guard below is for the one way left to get here, a hand-crafted route.
     */
    public function __construct(
        private DecisionRepository $decisions,
        private TokenProvider $tokenProvider,
        private Settings $settings,
        private ?ShadcnModuleRenderer $renderer = null,
    ) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->renderer === null) {
            throw new RuntimeException(
                'The Jev module needs EXT:shadcn_ui, which is not loaded.',
                1758300000,
            );
        }

        return $this->renderer->render($request, new ShadcnApp(
            name: 'webcon_jev/decisions',
            jsModule: '@webconsulting/webcon-jev/jev-module.js',
            props: [
                'decisions' => array_map(
                    static fn(object $decision): array => $decision->toArray(),
                    $this->decisions->findAll(0, true),
                ),
                'connection' => [
                    'endpoint' => $this->settings->endpoint(),
                    'model' => $this->settings->model(),
                    'enabled' => $this->settings->isEnabled(),
                    'tokenSource' => $this->tokenProvider->describeSource(),
                    'hasToken' => $this->tokenProvider->hasToken(),
                ],
                'defaults' => [
                    'confidenceThreshold' => 0.6,
                    'cacheLifetime' => $this->settings->cacheLifetime(),
                    'maxCallsPerMinute' => $this->settings->maxCallsPerMinute(),
                ],
            ],
            layout: ShellLayout::ChatLeft,
            title: 'Jev decisions',
        ));
    }
}
