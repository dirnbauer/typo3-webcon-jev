<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Editing\DecisionUsage;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Admin > Jev decisions > Decisions: the list, and the editor with its playground.
 */
#[AsController]
final readonly class DecisionsController
{
    public const string MODULE = 'webcon_jev_decisions';
    public const string EDIT_ROUTE = self::MODULE . '.edit';

    /** The id the editor's form carries, so the DocHeader's save button can submit it. */
    public const string FORM_ID = 'webcon-jev-decision-form';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private ComponentFactory $componentFactory,
        private IconFactory $iconFactory,
        private UriBuilder $uriBuilder,
        private DecisionRepository $decisions,
        private DecisionUsage $usage,
        private RunLogger $runLogger,
        private TokenProvider $tokenProvider,
        private Settings $settings,
        private Labels $labels,
    ) {}

    public function listAction(ServerRequestInterface $request): ResponseInterface
    {
        $title = $this->labels->get('decisions.title');
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->labels->get('module.title'), $title);
        $view->makeDocHeaderModuleMenu();
        $view->getDocHeaderComponent()->setShortcutContext(self::MODULE, $title);

        $newButton = $this->componentFactory->createLinkButton()
            ->setHref($this->url(self::EDIT_ROUTE))
            ->setTitle($this->labels->get('decisions.new'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL));
        $view->addButtonToButtonBar($newButton, ButtonBar::BUTTON_POSITION_LEFT, 1);

        $decisions = $this->decisions->findAll(0, true);
        $uids = array_map(static fn(Decision $decision): int => $decision->uid, $decisions);
        $usage = $this->usage->countFor($uids);
        $runs = $this->runLogger->summaryByDecision(time() - 30 * 86400);
        $listUrl = $this->url(self::MODULE);

        return $view->assignMultiple([
            'rows' => array_map(fn(Decision $decision): array => [
                'decision' => $decision->toArray(),
                'usage' => $usage[$decision->uid] ?? ['forms' => 0, 'rules' => 0],
                'runs' => $runs[$decision->uid] ?? null,
                'editUrl' => $this->url(self::EDIT_ROUTE, ['decision' => $decision->uid]),
                'tryUrl' => $this->url(self::EDIT_ROUTE, ['decision' => $decision->uid]) . '#webcon-jev-playground',
                'runLogUrl' => $this->url(RunLogController::MODULE, ['filter' => ['decision' => $decision->uid]]),
                'recordUrl' => $this->recordEditUrl($decision->uid, $listUrl),
            ], $decisions),
            'newUrl' => $this->url(self::EDIT_ROUTE),
            'connectionUrl' => $this->url(ConnectionController::MODULE),
            'deleteUrl' => $this->url('ajax_webcon_jev_decision_delete'),
            'tokenPresent' => $this->tokenProvider->isPresent(),
            'enabled' => $this->settings->isEnabled(),
        ])->renderResponse('Decisions/List');
    }

    public function editAction(ServerRequestInterface $request): ResponseInterface
    {
        $uid = max(0, Cast::int($request->getQueryParams()['decision'] ?? null));
        $decision = $uid > 0 ? $this->decisions->findByUid($uid, 0, true) : null;
        $listUrl = $this->url(self::MODULE);

        if ($uid > 0 && $decision === null) {
            $view = $this->moduleTemplateFactory->create($request);
            $view->addFlashMessage(
                $this->labels->get('editor.notFound.message', [$uid]),
                $this->labels->get('editor.notFound.title'),
                ContextualFeedbackSeverity::WARNING,
            );

            return new RedirectResponse($listUrl);
        }

        $title = $decision !== null
            ? $this->labels->get('editor.title.edit', [$decision->title !== '' ? $decision->title : $decision->identifier])
            : $this->labels->get('editor.title.new');

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->labels->get('module.title'), $title);
        $this->editorButtons($view, $decision, $listUrl);

        $usage = $decision !== null ? ($this->usage->countFor([$decision->uid])[$decision->uid] ?? null) : null;

        return $view->assignMultiple([
            'formId' => self::FORM_ID,
            'config' => [
                'formId' => self::FORM_ID,
                'decision' => $decision?->toArray(),
                'defaults' => [
                    'confidenceThreshold' => Decision::DEFAULT_CONFIDENCE_THRESHOLD,
                    'cacheLifetime' => $this->settings->cacheLifetime(),
                    'model' => $this->settings->model(),
                ],
                'status' => [
                    'tokenPresent' => $this->tokenProvider->isPresent(),
                    'enabled' => $this->settings->isEnabled(),
                ],
                'usage' => $usage ?? ['forms' => 0, 'rules' => 0],
                'urls' => [
                    'save' => $this->url('ajax_webcon_jev_decision_save'),
                    'delete' => $this->url('ajax_webcon_jev_decision_delete'),
                    'playground' => $this->url('ajax_webcon_jev_playground'),
                    'list' => $listUrl,
                    'connection' => $this->url(ConnectionController::MODULE),
                    'runLog' => $decision !== null
                        ? $this->url(RunLogController::MODULE, ['filter' => ['decision' => $decision->uid]])
                        : '',
                ],
            ],
        ])->renderResponse('Decisions/Edit');
    }

    private function editorButtons(ModuleTemplate $view, ?Decision $decision, string $listUrl): void
    {
        $view->addButtonToButtonBar(
            $this->componentFactory->createCloseButton($listUrl),
            ButtonBar::BUTTON_POSITION_LEFT,
            1,
        );
        $view->addButtonToButtonBar(
            $this->componentFactory->createSaveButton(self::FORM_ID),
            ButtonBar::BUTTON_POSITION_LEFT,
            2,
        );

        if ($decision === null) {
            return;
        }

        // The editor listens for this button; it asks before it deletes anything.
        $delete = $this->componentFactory->createGenericButton()
            ->setTag('button')
            ->setLabel($this->labels->get('editor.delete'))
            ->setTitle($this->labels->get('editor.delete'))
            ->setIcon($this->iconFactory->getIcon('actions-delete', IconSize::SMALL))
            ->setShowLabelText(true)
            ->setAttributes(['type' => 'button', 'data-webcon-jev-action' => 'delete']);
        $view->addButtonToButtonBar($delete, ButtonBar::BUTTON_POSITION_LEFT, 3);

        $record = $this->componentFactory->createLinkButton()
            ->setHref($this->recordEditUrl($decision->uid, $this->url(self::EDIT_ROUTE, ['decision' => $decision->uid])))
            ->setTitle($this->labels->get('editor.recordEditor'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-window-open', IconSize::SMALL));
        $view->addButtonToButtonBar($record, ButtonBar::BUTTON_POSITION_RIGHT, 1);

        $view->getDocHeaderComponent()->setShortcutContext(
            self::EDIT_ROUTE,
            $decision->title,
            ['decision' => $decision->uid],
        );
    }

    /**
     * The record editor, for what this module leaves to it: translations, history, access.
     */
    private function recordEditUrl(int $uid, string $returnUrl): string
    {
        return $this->url('record_edit', [
            'edit' => ['tx_webconjev_decision' => [$uid => 'edit']],
            'returnUrl' => $returnUrl,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function url(string $route, array $parameters = []): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute($route, $parameters);
    }
}
