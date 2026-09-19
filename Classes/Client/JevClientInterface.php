<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client;

use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\Question;

/**
 * Puts typed questions to a System One model and gets typed answers back.
 */
interface JevClientInterface
{
    /**
     * @param string|array<mixed>|null $state     The program state to evaluate — text, or a JSON-serialisable structure
     * @param array<string, Question>  $questions Keyed by the name the answer comes back under
     *
     * @throws \Webconsulting\WebconJev\Exception\JevException
     */
    public function ask(string|array|null $state, array $questions, ?string $model = null): DecisionResult;

    /**
     * Whether a token is available, so a caller can skip the call instead of handling the failure.
     */
    public function isConfigured(): bool;
}
