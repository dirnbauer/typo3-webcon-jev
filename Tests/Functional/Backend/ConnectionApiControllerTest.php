<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Backend\Controller\ConnectionApiController;
use Webconsulting\WebconJev\Backend\Labels;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Exception\AuthenticationException;
use Webconsulting\WebconJev\Service\ConnectionProbe;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Tests\Double\FakeJevClient;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * "Send a test question": a failed check is still a successful request, so every answer is a 200
 * that says yes or no, and why.
 */
final class ConnectionApiControllerTest extends AbstractJevTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdministrator();
        putenv(TokenProvider::ENVIRONMENT_VARIABLE);
    }

    protected function tearDown(): void
    {
        putenv(TokenProvider::ENVIRONMENT_VARIABLE);
        parent::tearDown();
    }

    #[Test]
    public function withoutATokenNothingIsSentAndTheAnswerSaysHowToAddOne(): void
    {
        $client = new FakeJevClient(self::answered());

        $body = $this->ping($client);

        self::assertFalse($body['ok']);
        self::assertStringContainsString('webcon-jev:token:import', (string)$body['message']);
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aWorkingConnectionReportsTheModelAndTheAnswer(): void
    {
        putenv(TokenProvider::ENVIRONMENT_VARIABLE . '=test-token');

        $body = $this->ping(new FakeJevClient(self::answered()));

        self::assertTrue($body['ok']);
        self::assertSame('The token, the endpoint and the network work: an answer came back in 88 ms from jev-test.', $body['message']);
        self::assertIsArray($body['result']);
        self::assertSame('annoyed', $body['result']['answers']['mood']['value']);
    }

    #[Test]
    public function aRefusedTokenIsReportedWithTheApisReason(): void
    {
        putenv(TokenProvider::ENVIRONMENT_VARIABLE . '=revoked-token');

        $body = $this->ping(new FakeJevClient(new AuthenticationException('Jev rejected the API token: revoked')));

        self::assertFalse($body['ok']);
        self::assertSame('Jev rejected the API token: revoked', $body['detail']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ping(FakeJevClient $client): array
    {
        $controller = new ConnectionApiController(
            new ConnectionProbe($client),
            $this->get(TokenProvider::class),
            $this->get(Labels::class),
        );
        $response = $controller->pingAction($this->backendRequest('ajax_webcon_jev_ping', json: []));
        self::assertSame(200, $response->getStatusCode());

        return self::decodeJson((string)$response->getBody());
    }

    private static function answered(): DecisionResult
    {
        return new DecisionResult(
            [ConnectionProbe::QUESTION => Answer::fromResponse(ConnectionProbe::QUESTION, QuestionType::Choice, [
                'choice' => 'annoyed',
                'confidence' => 0.97,
            ])],
            'jev-test',
            new Usage(95),
            durationMs: 87.6,
        );
    }
}
