<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\JevClientInterface;

/**
 * One real, deliberately trivial call — so "is it working" has an answer that does not depend on
 * a form. The CLI ping and the module's connection check ask the same question.
 */
final readonly class ConnectionProbe
{
    /** The name the probe's answer comes back under. */
    public const string QUESTION = 'mood';

    public function __construct(private JevClientInterface $client) {}

    /**
     * @throws \Webconsulting\WebconJev\Exception\JevException when the token, the endpoint or the network fails
     */
    public function ask(): DecisionResult
    {
        return $this->client->ask(
            'The delivery arrived three days late and the box was crushed.',
            [
                self::QUESTION => new Question(
                    name: self::QUESTION,
                    type: QuestionType::Choice,
                    instructions: 'How does the writer feel about what happened?',
                    criteria: [
                        'happy' => 'Pleased with how it went',
                        'annoyed' => 'Unhappy about a problem',
                        'neutral' => 'Reporting without feeling either way',
                    ],
                ),
            ],
        );
    }
}
