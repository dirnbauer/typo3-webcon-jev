<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Pagination\QueryBuilderPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Service\Dto\RunLogFilter;
use Webconsulting\WebconJev\Service\Dto\RunOutcome;
use Webconsulting\WebconJev\Service\Dto\RunRecord;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Admin > Jev decisions > Run log: every call, what it answered, and what it cost.
 *
 * A form that quietly fell back to its default receiver for a week looks exactly like a form that
 * worked — until you read this.
 */
#[AsController]
final readonly class RunLogController
{
    public const string MODULE = 'webcon_jev_runs';

    private const int ITEMS_PER_PAGE = 50;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private DecisionRepository $decisions,
        private RunLogger $runLogger,
        private Labels $labels,
    ) {}

    public function listAction(ServerRequestInterface $request): ResponseInterface
    {
        // The filter form posts (a GET form would drop the route token from its action); answer
        // with a redirect to the same filter as a link, so the result can be reloaded and shared.
        if ($request->getMethod() === 'POST') {
            $posted = RunLogFilter::fromArray(Cast::map(Cast::map($request->getParsedBody())['filter'] ?? null));

            return new RedirectResponse($this->url(self::MODULE, array_filter(['filter' => $posted->toArray()])), 303);
        }

        $query = $request->getQueryParams();
        $filter = RunLogFilter::fromArray(Cast::map($query['filter'] ?? null));
        $page = max(1, Cast::int($query['page'] ?? null, 1));

        $title = $this->labels->get('runs.title');
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->labels->get('module.title'), $title);
        $view->makeDocHeaderModuleMenu();
        $view->getDocHeaderComponent()->setShortcutContext(
            self::MODULE,
            $title,
            array_filter(['filter' => $filter->toArray()]),
        );

        $paginator = new QueryBuilderPaginator($this->runLogger->query($filter), $page, self::ITEMS_PER_PAGE);
        $pagination = new SimplePagination($paginator);
        $runs = [];
        foreach ($paginator->getPaginatedItems() as $row) {
            $runs[] = RunRecord::fromRow(Cast::map($row));
        }

        $decisions = $this->decisions->findAll(0, true);
        $titles = [];
        foreach ($decisions as $decision) {
            $titles[$decision->uid] = $decision->title !== '' ? $decision->title : $decision->identifier;
        }

        return $view->assignMultiple([
            'runs' => array_map(fn(RunRecord $run): array => [
                'run' => $run,
                'decisionTitle' => $titles[$run->decision] ?? '',
                'decisionUrl' => isset($titles[$run->decision])
                    ? $this->url(DecisionsController::EDIT_ROUTE, ['decision' => $run->decision])
                    : '',
            ], $runs),
            'filter' => [
                'decision' => $filter->decision,
                'context' => $filter->context,
                'outcome' => $filter->outcome->value ?? '',
                'active' => $filter->isActive(),
            ],
            'decisions' => array_map(
                static fn(Decision $decision): array => ['uid' => $decision->uid, 'title' => $titles[$decision->uid] ?? ''],
                $decisions,
            ),
            'contexts' => $this->runLogger->contexts(),
            'outcomes' => array_map(static fn(RunOutcome $outcome): string => $outcome->value, RunOutcome::cases()),
            'paginator' => $paginator,
            'pagination' => $pagination,
            'pageUrls' => $this->pageUrls($pagination, $filter),
            'resetUrl' => $this->url(self::MODULE),
            'formUrl' => $this->url(self::MODULE),
            'decisionsUrl' => $this->url(DecisionsController::MODULE),
        ])->renderResponse('RunLog/List');
    }

    /**
     * Links to the first, previous, next and last page — only those that exist.
     *
     * @return array{first?: string, previous?: string, next?: string, last?: string}
     */
    private function pageUrls(SimplePagination $pagination, RunLogFilter $filter): array
    {
        $pages = [
            'first' => $pagination->getFirstPageNumber(),
            'previous' => $pagination->getPreviousPageNumber(),
            'next' => $pagination->getNextPageNumber(),
            'last' => $pagination->getLastPageNumber(),
        ];

        $urls = [];
        foreach ($pages as $name => $page) {
            if ($page !== null && $page !== $pagination->getPaginator()->getCurrentPageNumber()) {
                $urls[$name] = $this->url(self::MODULE, array_filter(['filter' => $filter->toArray(), 'page' => $page]));
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function url(string $route, array $parameters = []): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute($route, $parameters);
    }
}
