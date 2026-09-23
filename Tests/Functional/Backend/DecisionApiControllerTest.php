<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Backend\Controller\DecisionApiController;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * The editor's save and delete endpoints: what they write, and what they answer when they refuse.
 */
final class DecisionApiControllerTest extends AbstractJevTestCase
{
    private DecisionApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->controller = $this->get(DecisionApiController::class);
    }

    #[Test]
    public function aValidDecisionIsSavedAndComesBackWithItsUidsAndLinks(): void
    {
        $response = $this->controller->saveAction($this->backendRequest('ajax_webcon_jev_decision_save', json: [
            'decision' => [
                'uid' => 0,
                'title' => 'Press desk',
                'identifier' => 'press_desk',
                'confidenceThreshold' => '0.8',
                'cacheLifetime' => '-1',
                'questions' => [[
                    'uid' => 0,
                    'name' => 'outlet',
                    'type' => 'choice',
                    'instructions' => 'Which kind of outlet is writing?',
                    'criteria' => [
                        ['uid' => 0, 'identifier' => 'print', 'description' => 'A newspaper or magazine', 'outcomeValue' => 'press@example.com'],
                        ['uid' => 0, 'identifier' => 'blog', 'description' => 'A blog or a newsletter', 'outcomeValue' => ''],
                    ],
                ]],
            ],
        ]));

        self::assertSame(200, $response->getStatusCode());
        $body = self::decodeJson((string)$response->getBody());
        self::assertTrue($body['ok']);
        self::assertSame('“Press desk” is saved.', $body['message']);
        self::assertIsArray($body['decision']);
        self::assertGreaterThan(0, $body['decision']['uid']);
        self::assertSame('outlet', $body['decision']['questions'][0]['name']);
        self::assertGreaterThan(0, $body['decision']['questions'][0]['criteria'][1]['uid']);
        self::assertIsArray($body['urls']);
        self::assertStringContainsString('decision=' . $body['decision']['uid'], urldecode($body['urls']['edit']));
    }

    #[Test]
    public function anInvalidDecisionIsRefusedFieldByField(): void
    {
        $response = $this->controller->saveAction($this->backendRequest('ajax_webcon_jev_decision_save', json: [
            'decision' => [
                'uid' => 1,
                'title' => '',
                'questions' => [['uid' => 1, 'name' => 'department', 'type' => 'choice', 'instructions' => 'Which?', 'criteria' => []]],
            ],
        ]));

        self::assertSame(422, $response->getStatusCode());
        $body = self::decodeJson((string)$response->getBody());
        self::assertFalse($body['ok']);
        self::assertSame('2 fields need attention — the decision was not saved.', $body['message']);
        self::assertSame([
            ['path' => 'title', 'message' => 'Required.'],
            ['path' => 'questions.0.criteria', 'message' => 'A choice needs at least two options.'],
        ], $body['errors']);
        self::assertSame('Contact routing', $this->row('tx_webconjev_decision', 1)['title'], 'nothing was written');
    }

    #[Test]
    public function aRequestWithoutADecisionIsABadRequest(): void
    {
        $response = $this->controller->saveAction($this->backendRequest('ajax_webcon_jev_decision_save', json: ['title' => 'x']));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function aTranslationsUidDeletesTheDecisionNotJustTheTranslation(): void
    {
        $response = $this->controller->deleteAction($this->backendRequest('ajax_webcon_jev_decision_delete', json: ['uid' => 2]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, (int)$this->row('tx_webconjev_decision', 1)['deleted']);
        self::assertSame(1, (int)$this->row('tx_webconjev_decision', 2)['deleted']);
    }

    #[Test]
    public function deletingReportsWhatWentAndRefusesWhatIsNotThere(): void
    {
        $deleted = $this->controller->deleteAction($this->backendRequest('ajax_webcon_jev_decision_delete', json: ['uid' => 1]));
        self::assertSame(200, $deleted->getStatusCode());
        self::assertTrue(self::decodeJson((string)$deleted->getBody())['ok']);
        self::assertSame(1, (int)$this->row('tx_webconjev_decision', 1)['deleted']);

        $again = $this->controller->deleteAction($this->backendRequest('ajax_webcon_jev_decision_delete', json: ['uid' => 1]));
        self::assertSame(404, $again->getStatusCode());
    }
}
