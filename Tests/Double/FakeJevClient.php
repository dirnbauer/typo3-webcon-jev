<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Double;

use Throwable;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\JevClientInterface;

/**
 * A client that answers with what the test told it to, or throws it — and remembers what it was
 * asked, so a test can check the request as well as the handling of the answer.
 */
final class FakeJevClient implements JevClientInterface
{
    public int $calls = 0;

    /** @var string|array<mixed>|null */
    public string|array|null $lastState = null;

    /** @var array<string, Question> */
    public array $lastQuestions = [];

    public function __construct(
        private readonly DecisionResult|Throwable $response,
        private readonly bool $configured = true,
    ) {}

    public function ask(string|array|null $state, array $questions, ?string $model = null): DecisionResult
    {
        $this->calls++;
        $this->lastState = $state;
        $this->lastQuestions = $questions;

        if ($this->response instanceof Throwable) {
            throw $this->response;
        }

        return $this->response;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
