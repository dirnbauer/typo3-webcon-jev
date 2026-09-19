<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Exception\AuthenticationException;
use Webconsulting\WebconJev\Exception\InvalidQuestionException;
use Webconsulting\WebconJev\Exception\InvalidResponseException;
use Webconsulting\WebconJev\Exception\JevException;
use Webconsulting\WebconJev\Exception\NotConfiguredException;
use Webconsulting\WebconJev\Exception\RateLimitException;
use Webconsulting\WebconJev\Exception\UnavailableException;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Talks to https://api.typesafe.ai/v1/systemone.
 *
 * One request carries the state once and every question about it, because Jev evaluates them in a
 * single parallel pass — asking five questions separately costs five times the input tokens and
 * five times the latency for the same answers.
 */
final readonly class HttpJevClient implements JevClientInterface
{
    public function __construct(
        private RequestFactory $requestFactory,
        private TokenProvider $tokenProvider,
        private Settings $settings,
        private LoggerInterface $logger,
    ) {}

    public function isConfigured(): bool
    {
        return $this->settings->isEnabled() && $this->tokenProvider->hasToken();
    }

    public function ask(string|array|null $state, array $questions, ?string $model = null): DecisionResult
    {
        if ($questions === []) {
            throw new InvalidQuestionException('Asking Jev nothing is not a request.');
        }

        $token = $this->tokenProvider->getToken();
        if ($token === null) {
            throw new NotConfiguredException(
                'No Jev API token. Store one with "vendor/bin/typo3 webcon-jev:token:import".',
            );
        }

        $model ??= $this->settings->model();
        $payload = [
            'model' => $model,
            'state' => $state,
            'questions' => array_map(static fn(Question $q): array => $q->toPayload(), $questions),
        ];

        $startedAt = microtime(true);
        $response = $this->send($payload, $token);
        $durationMs = (microtime(true) - $startedAt) * 1000;

        return $this->parse($response, $questions, $model, $durationMs);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload, string $token): ResponseInterface
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidQuestionException('The state is not JSON-serialisable.', 0, $exception);
        }

        try {
            $response = $this->requestFactory->request($this->settings->endpoint(), 'POST', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => $body,
                'timeout' => $this->settings->timeout(),
                'http_errors' => false,
            ]);
        } catch (Throwable $exception) {
            throw new UnavailableException('Could not reach Jev: ' . $exception->getMessage(), 0, $exception);
        }

        $status = $response->getStatusCode();
        if ($status < 300) {
            return $response;
        }

        $detail = $this->errorDetail($response);

        throw match (true) {
            $status === 401, $status === 403 => new AuthenticationException(
                'Jev rejected the API token: ' . $detail,
            ),
            $status === 429, $status === 529 => new RateLimitException(
                'Jev is rate limiting or overloaded: ' . $detail,
            ),
            $status === 422 => new InvalidQuestionException('Jev rejected the questions: ' . $detail),
            $status >= 500 => new UnavailableException(sprintf('Jev returned HTTP %d: %s', $status, $detail)),
            default => new JevException(sprintf('Jev returned HTTP %d: %s', $status, $detail)),
        };
    }

    /**
     * @param array<string, Question> $questions
     */
    private function parse(
        ResponseInterface $response,
        array $questions,
        string $model,
        float $durationMs,
    ): DecisionResult {
        try {
            $decoded = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidResponseException('Jev did not answer with JSON.', 0, $exception);
        }

        $body = Cast::map($decoded);
        if ($body === [] || !is_array($body['answers'] ?? null)) {
            throw new InvalidResponseException('Jev answered without an "answers" object.');
        }
        $raw = Cast::map($body['answers']);

        $answers = [];
        foreach ($questions as $name => $question) {
            if (!is_array($raw[$name] ?? null)) {
                $this->logger->warning('Jev did not answer a question it was asked.', ['question' => $name]);
                continue;
            }
            $answers[$name] = Answer::fromResponse($name, $question->type, Cast::map($raw[$name]));
        }

        return new DecisionResult(
            answers: $answers,
            model: Cast::trimmed($body['model'] ?? null, $model),
            usage: Usage::fromResponse(Cast::map($body['usage'] ?? null)),
            durationMs: $durationMs,
        );
    }

    private function errorDetail(ResponseInterface $response): string
    {
        $body = (string)$response->getBody();
        $decoded = Cast::map(json_decode($body, true));
        foreach (['error', 'message', 'detail'] as $key) {
            $detail = Cast::trimmed($decoded[$key] ?? null);
            if ($detail !== '') {
                return $detail;
            }
        }

        return mb_substr(trim($body), 0, 500);
    }
}
