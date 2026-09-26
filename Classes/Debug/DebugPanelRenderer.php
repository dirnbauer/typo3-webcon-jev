<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders the debug panel as one self-contained HTML fragment: it links its own stylesheet, so
 * it looks the same appended to a page or dropped into a form from the condition endpoint.
 */
final readonly class DebugPanelRenderer
{
    public function __construct(
        private ViewFactoryInterface $viewFactory,
        private DebugPresenter $presenter,
    ) {}

    public function render(DebugLog $log, ServerRequestInterface $request): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:webcon_jev/Resources/Private/Templates'],
            partialRootPaths: ['EXT:webcon_jev/Resources/Private/Partials'],
            request: $request,
        ));
        $view->assignMultiple($this->presenter->present($log));

        return trim($view->render('Debug/Panel'));
    }
}
