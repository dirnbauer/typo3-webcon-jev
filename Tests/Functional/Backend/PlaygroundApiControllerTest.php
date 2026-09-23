<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Webconsulting\WebconJev\Backend\Controller\PlaygroundApiController;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Editing\DecisionValidator;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\StateBuilder;
use Webconsulting\WebconJev\Tests\Double\ArrayCache;
use Webconsulting\WebconJev\Tests\Double\FakeJevClient;
use Webconsulting\WebconJev\Tests\Double\TestSettings;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * The playground runs what the editor holds — saved or not — against a real repository, validator
 * and run log. Only the API is a stand-in.
 */
final class PlaygroundApiControllerTest extends AbstractJevTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
    }

    #[Test]
    public function anUnsavedDraftIsAskedWithTheStateItsTemplateBuilds(): void
    {
        $client = new FakeJevClient(self::answered('support', 0.92));

        $response = $this->controller($client)->runAction($this->backendRequest('ajax_webcon_jev_playground', json: [
            'draft' => self::draft(),
            'context' => ['field' => ['subject' => 'Invoice 4711', 'message' => 'Charged twice.']],
        ]));

        self::assertSame(200, $response->getStatusCode());
        $body = self::decodeJson((string)$response->getBody());
        self::assertTrue($body['ok']);
        self::assertSame("Subject: Invoice 4711\nMessage: Charged twice.", $body['state']);
        self::assertSame($body['state'], $client->lastState, 'what the response shows is what was sent');
        self::assertSame(['department' => ['outcome' => 'support@example.com', 'isDefault' => false]], $body['outcomes']);
        self::assertFalse($body['needsHumanReview']);

        $run = $this->row(RunLogger::TABLE, 1);
        self::assertSame('playground', $run['context']);
        self::assertSame('triage', $run['decision_identifier']);
    }

    #[Test]
    public function anUncertainAnswerShowsTheDefaultOutcomeStandingIn(): void
    {
        $response = $this->controller(new FakeJevClient(self::answered('sales', 0.41)))->runAction(
            $this->backendRequest('ajax_webcon_jev_playground', json: ['draft' => self::draft(), 'state' => 'Hello']),
        );

        $body = self::decodeJson((string)$response->getBody());
        self::assertSame(['department' => ['outcome' => 'office@example.com', 'isDefault' => true]], $body['outcomes']);
        self::assertTrue($body['needsHumanReview']);
    }

    #[Test]
    public function thePlaygroundNeverAnswersFromTheCache(): void
    {
        $client = new FakeJevClient(self::answered('sales', 0.9));
        $controller = $this->controller($client, cacheLifetime: 300);
        $payload = ['draft' => self::draft(), 'context' => ['field' => ['subject' => 'Same', 'message' => 'Same']]];

        $controller->runAction($this->backendRequest('ajax_webcon_jev_playground', json: $payload));
        $controller->runAction($this->backendRequest('ajax_webcon_jev_playground', json: $payload));

        self::assertSame(2, $client->calls);
    }

    #[Test]
    public function aDraftJevWouldRefuseIsSentBackWithTheFieldsToFix(): void
    {
        $draft = self::draft();
        $draft['questions'][0]['criteria'] = [['identifier' => 'sales', 'description' => 'Buying']];
        $client = new FakeJevClient(self::answered('sales', 0.9));

        $response = $this->controller($client)->runAction($this->backendRequest('ajax_webcon_jev_playground', json: ['draft' => $draft]));

        self::assertSame(422, $response->getStatusCode());
        $body = self::decodeJson((string)$response->getBody());
        self::assertSame([['path' => 'questions.0.criteria', 'message' => 'A choice needs at least two options.']], $body['errors']);
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aStoredDecisionCanStillBeRunByUid(): void
    {
        $client = new FakeJevClient(self::answered('sales', 0.9));

        $found = $this->controller($client)->runAction($this->backendRequest('ajax_webcon_jev_playground', json: ['decision' => 1, 'state' => 'I want to buy']));
        self::assertSame(200, $found->getStatusCode());
        self::assertSame('Message: I want to buy', self::decodeJson((string)$found->getBody())['state']);

        $missing = $this->controller($client)->runAction($this->backendRequest('ajax_webcon_jev_playground', json: ['decision' => 99, 'state' => 'x']));
        self::assertSame(404, $missing->getStatusCode());
    }

    private function controller(FakeJevClient $client, int $cacheLifetime = 0): PlaygroundApiController
    {
        $settings = TestSettings::with(['cacheLifetime' => (string)$cacheLifetime, 'maxCallsPerMinute' => '0']);
        $runner = new DecisionRunner(
            $client,
            new StateBuilder(),
            $this->get(RunLogger::class),
            $settings,
            new ArrayCache(),
            new NullLogger(),
        );

        return new PlaygroundApiController(
            $this->get(DecisionRepository::class),
            $this->get(DecisionValidator::class),
            $runner,
            new StateBuilder(),
            $this->get(Labels::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function draft(): array
    {
        return [
            'uid' => 0,
            'title' => 'Triage',
            'identifier' => 'triage',
            'stateTemplate' => "Subject: {{field.subject}}\nMessage: {{field.message}}",
            'confidenceThreshold' => '0.6',
            'cacheLifetime' => '-1',
            'defaultOutcome' => 'office@example.com',
            'questions' => [[
                'uid' => 0,
                'name' => 'department',
                'type' => 'choice',
                'instructions' => 'Which department should answer?',
                'criteria' => [
                    ['uid' => 0, 'identifier' => 'sales', 'description' => 'Wants to buy', 'outcomeValue' => 'sales@example.com'],
                    ['uid' => 0, 'identifier' => 'support', 'description' => 'Something is broken', 'outcomeValue' => 'support@example.com'],
                ],
            ]],
        ];
    }

    private static function answered(string $choice, float $confidence): DecisionResult
    {
        return new DecisionResult(
            ['department' => Answer::fromResponse('department', QuestionType::Choice, [
                'choice' => $choice,
                'confidence' => $confidence,
                'probabilities' => ['sales' => 1 - $confidence, 'support' => $confidence],
            ])],
            'jev-test',
            new Usage(120),
            durationMs: 42.0,
        );
    }
}
