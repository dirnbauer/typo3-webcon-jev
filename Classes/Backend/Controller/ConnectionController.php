<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\TokenProvider;

/**
 * Admin > Jev decisions > Connection: whether this installation can reach Jev, and what it has cost.
 */
#[AsController]
final readonly class ConnectionController
{
    public const string MODULE = 'webcon_jev_connection';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private TokenProvider $tokenProvider,
        private RunLogger $runLogger,
        private Settings $settings,
        private Labels $labels,
    ) {}

    public function showAction(ServerRequestInterface $request): ResponseInterface
    {
        $title = $this->labels->get('connection.title');
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->labels->get('module.title'), $title);
        $view->makeDocHeaderModuleMenu();
        $view->getDocHeaderComponent()->setShortcutContext(self::MODULE, $title);

        $now = time();

        return $view->assignMultiple([
            'connection' => [
                'endpoint' => $this->settings->endpoint(),
                'model' => $this->settings->model(),
                'enabled' => $this->settings->isEnabled(),
                'tokenSource' => $this->tokenProvider->source()->value,
                'tokenIdentifier' => $this->settings->tokenIdentifier(),
                'hasToken' => $this->tokenProvider->hasToken(),
                // The one that matters for the powermail integrations: they run without a backend user.
                'frontendReadable' => $this->tokenProvider->isReadableByFrontend(),
                'timeout' => $this->settings->timeout(),
                'cacheLifetime' => $this->settings->cacheLifetime(),
                'maxCallsPerMinute' => $this->settings->maxCallsPerMinute(),
                'logRuns' => $this->settings->logRuns(),
                'logRetentionDays' => $this->settings->logRetentionDays(),
            ],
            'totals' => [
                'day' => $this->runLogger->totalsSince($now - 86400),
                'month' => $this->runLogger->totalsSince($now - 30 * 86400),
            ],
            'pingUrl' => (string)$this->uriBuilder->buildUriFromRoute('ajax_webcon_jev_ping'),
            'runLogUrl' => (string)$this->uriBuilder->buildUriFromRoute(RunLogController::MODULE),
        ])->renderResponse('Connection/Show');
    }
}
