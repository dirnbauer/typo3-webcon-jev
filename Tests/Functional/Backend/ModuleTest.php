<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleRegistry;
use TYPO3\CMS\Backend\Routing\Router;
use Webconsulting\WebconJev\Backend\Controller\ConnectionController;
use Webconsulting\WebconJev\Backend\Controller\DecisionsController;
use Webconsulting\WebconJev\Backend\Controller\RunLogController;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * The module is a native backend module: registered under Admin with three pages, reachable only
 * by those who may open it, and rendered from its Fluid templates without any other extension.
 */
final class ModuleTest extends AbstractJevTestCase
{
    #[Test]
    public function theModuleAndItsPagesAreRegisteredForAdministrators(): void
    {
        $registry = $this->get(ModuleRegistry::class);

        $group = $registry->getModule('webcon_jev');
        self::assertSame('admin', $group->getParentIdentifier());
        self::assertSame('admin', $group->getAccess());
        self::assertSame($group, $registry->getModule('tools_webconjev'), 'the identifier before 0.2.0 still leads here');

        foreach ([DecisionsController::MODULE, RunLogController::MODULE, ConnectionController::MODULE] as $identifier) {
            $module = $registry->getModule($identifier);
            self::assertSame('webcon_jev', $module->getParentIdentifier(), $identifier);
            self::assertSame('admin', $module->getAccess(), $identifier);
        }
    }

    #[Test]
    public function everyEndpointInheritsTheModulesAccessAndOnlyTakesPost(): void
    {
        $router = $this->get(Router::class);

        foreach (['decision_save', 'decision_delete', 'playground', 'ping'] as $name) {
            $route = $router->getRoute('ajax_webcon_jev_' . $name);
            self::assertNotNull($route, $name);
            self::assertSame(DecisionsController::MODULE, $route->getOption('inheritAccessFromModule'), $name);
            self::assertSame(['POST'], $route->getMethods(), $name);
        }
    }

    #[Test]
    public function withoutDecisionsTheListOffersToCreateTheFirst(): void
    {
        $this->signInAsAdministrator();

        $html = $this->render(DecisionsController::MODULE);

        self::assertStringContainsString('No decisions yet', $html);
        self::assertStringContainsString('Create the first decision', $html);
        self::assertStringContainsString('No API token', $html);
    }

    #[Test]
    public function theListShowsEachDecisionWithItsQuestionsUsageAndRuns(): void
    {
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->logRun(1, fallback: true);

        $html = $this->render(DecisionsController::MODULE);

        self::assertStringContainsString('Contact routing', $html);
        self::assertStringContainsString('<span class="webcon-jev-mono">department</span> · Choice', $html);
        self::assertStringContainsString('<span class="webcon-jev-mono">is_bug</span> · Noul', $html);
        self::assertStringContainsString('Disabled', $html, 'the hidden decision says so');
        self::assertStringContainsString('1 run', $html);
        self::assertStringContainsString('1 fallback', $html);
        self::assertStringContainsString('Not used by any form', $html);
        self::assertStringContainsString('data-webcon-jev-delete="1"', $html);
        self::assertStringNotContainsString('Deleted', $html, 'deleted decisions stay out');
        self::assertStringNotContainsString('Kontakt-Routing', $html, 'a translation is not a decision of its own');
        self::assertSame(2, substr_count($html, 'data-webcon-jev-decision="'), 'decisions 1 and 3, not the translation 2');
    }

    #[Test]
    public function theEditorReceivesTheDecisionAndItsEndpoints(): void
    {
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');

        $html = $this->render(DecisionsController::EDIT_ROUTE, ['decision' => 1]);

        self::assertSame(1, preg_match('/<webcon-jev-decision-editor config="([^"]+)">/', $html, $match));
        $config = self::decodeJson(html_entity_decode($match[1]));
        self::assertIsArray($config['decision']);
        self::assertSame('routing', $config['decision']['identifier']);
        self::assertSame(DecisionsController::FORM_ID, $config['formId']);
        self::assertIsArray($config['urls']);
        self::assertStringContainsString('/webcon-jev/decision/save', urldecode($config['urls']['save']));
        self::assertStringContainsString('form="' . DecisionsController::FORM_ID . '"', $html, 'the DocHeader save button submits the editor');
        self::assertStringContainsString('data-webcon-jev-action="delete"', $html);
    }

    #[Test]
    public function aNewDecisionStartsEmptyAndAMissingOneLeadsBackToTheList(): void
    {
        $this->signInAsAdministrator();

        $html = $this->render(DecisionsController::EDIT_ROUTE);
        self::assertStringContainsString('&quot;decision&quot;:null', $html);
        self::assertStringNotContainsString('data-webcon-jev-action="delete"', $html, 'nothing to delete yet');

        $response = $this->get(DecisionsController::class)->editAction(
            $this->backendRequest(DecisionsController::EDIT_ROUTE, ['decision' => 404]),
        );
        self::assertContains($response->getStatusCode(), [302, 303]);
        self::assertStringContainsString('/module/webcon-jev/decisions', urldecode($response->getHeaderLine('Location')));
    }

    #[Test]
    public function theRunLogListsEveryRunWithItsAnswerOrItsReason(): void
    {
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->logRun(1, fallback: false);
        $this->logRun(1, fallback: true);

        $html = $this->render(RunLogController::MODULE);

        self::assertStringContainsString('department=sales', $html);
        self::assertStringContainsString('the API was slow', $html);
        self::assertStringContainsString('Contact routing', $html);
        self::assertStringContainsString('Condition', $html);
    }

    #[Test]
    public function theRunLogTellsAdHocDecisionsFromDeletedOnesAndLabelsIntegrationContexts(): void
    {
        $this->signInAsAdministrator();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['webcon_jev']['runContexts']['my_extension_import']
            = 'LLL:EXT:webcon_jev/Tests/Functional/Fixtures/Language/contexts.xlf:jev.context';
        $logger = $this->get(RunLogger::class);
        $logger->log(new Decision(0, 'my_extension.content_type', '', '', '', '', 0.6, -1, 'text', []), DecisionResult::fallback('built in code'), 'my_extension_import');
        $logger->log(new Decision(99, 'retired', '', '', '', '', 0.6, -1, '', []), DecisionResult::fallback('stored once'), 'somebody_elses');

        $html = $this->render(RunLogController::MODULE);

        self::assertSame(1, substr_count($html, 'Ad-hoc decision'), 'uid 0: built in code, never stored');
        self::assertStringContainsString('my_extension.content_type', $html);
        self::assertSame(1, substr_count($html, 'Deleted decision'), 'only the stored decision that is gone');
        self::assertSame(2, substr_count($html, 'Document import'), 'the label the integration registered, in the row and the filter');
        self::assertStringNotContainsString('>my_extension_import<', $html);
        self::assertStringContainsString('<option value="my_extension_import"', $html, 'the filter still selects by the context itself');
        self::assertStringContainsString('>somebody_elses<', $html, 'a context nobody labelled is shown as written');
    }

    #[Test]
    public function theRunLogFiltersByResult(): void
    {
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->logRun(1, fallback: false);
        $this->logRun(1, fallback: true);

        $html = $this->render(RunLogController::MODULE, ['filter' => ['outcome' => 'fallback']]);

        self::assertStringContainsString('the API was slow', $html);
        self::assertStringNotContainsString('department=sales', $html);
    }

    #[Test]
    public function aFilterWithoutMatchesOffersToReset(): void
    {
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->logRun(1, fallback: false);

        $html = $this->render(RunLogController::MODULE, ['filter' => ['decision' => 3]]);

        self::assertStringContainsString('No runs match this filter', $html);
        self::assertStringContainsString('Reset filter', $html);
    }

    #[Test]
    public function aPostedFilterIsRedirectedToALink(): void
    {
        $this->signInAsAdministrator();

        $request = $this->backendRequest(RunLogController::MODULE)
            ->withMethod('POST')
            ->withParsedBody(['filter' => ['decision' => '2', 'outcome' => 'cached', 'context' => 'nonsense!']]);
        $response = $this->get(RunLogController::class)->listAction($request);

        self::assertSame(303, $response->getStatusCode());
        $location = urldecode($response->getHeaderLine('Location'));
        self::assertStringContainsString('filter[decision]=2', $location);
        self::assertStringContainsString('filter[outcome]=cached', $location);
        self::assertStringNotContainsString('nonsense', $location);
    }

    #[Test]
    public function theConnectionPageSaysWhatIsMissing(): void
    {
        $this->signInAsAdministrator();

        $html = $this->render(ConnectionController::MODULE);

        self::assertStringContainsString('https://api.typesafe.ai/v1/systemone', $html);
        self::assertStringContainsString('No API token', $html);
        self::assertStringContainsString('data-webcon-jev-ping=', $html);
        self::assertStringContainsString('Last 30 days', $html);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function render(string $route, array $query = []): string
    {
        $request = $this->backendRequest($route, $query);
        $response = match ($route) {
            DecisionsController::MODULE => $this->get(DecisionsController::class)->listAction($request),
            DecisionsController::EDIT_ROUTE => $this->get(DecisionsController::class)->editAction($request),
            RunLogController::MODULE => $this->get(RunLogController::class)->listAction($request),
            ConnectionController::MODULE => $this->get(ConnectionController::class)->showAction($request),
            default => self::fail('no controller for ' . $route),
        };
        self::assertSame(200, $response->getStatusCode(), $route);

        return (string)$response->getBody();
    }

    private function logRun(int $decisionUid, bool $fallback): void
    {
        $decision = $this->get(DecisionRepository::class)->findByUid($decisionUid, 0, true);
        self::assertNotNull($decision);

        $result = $fallback
            ? DecisionResult::fallback('the API was slow')
            : new DecisionResult(
                ['department' => Answer::fromResponse('department', QuestionType::Choice, ['choice' => 'sales', 'confidence' => 0.88])],
                'jev-test',
                new Usage(210),
                durationMs: 640.0,
            );
        $this->get(RunLogger::class)->log($decision, $result, RunLogger::CONTEXT_CONDITION, 'form 5, rule 2');
    }
}
