<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\JevClientInterface;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Exception\JevException;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Asks Jev one trivial question, to prove the token, the endpoint and the network all work.
 */
#[AsCommand(
    name: 'webcon-jev:ping',
    description: 'Check that the configured Jev token can reach the API',
)]
final class PingCommand extends Command
{
    public function __construct(
        private readonly JevClientInterface $client,
        private readonly TokenProvider $tokenProvider,
        private readonly Settings $settings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->definitionList(
            ['Endpoint' => $this->settings->endpoint()],
            ['Model' => $this->settings->model()],
            ['Token' => $this->tokenProvider->describeSource()],
            ['Enabled' => $this->settings->isEnabled() ? 'yes' : 'no'],
        );

        if (!$this->tokenProvider->hasToken()) {
            $io->error('No token. Run "webcon-jev:token:import" after setting TYPESAFE_API_KEY.');

            return Command::FAILURE;
        }

        try {
            $result = $this->client->ask(
                'The delivery arrived three days late and the box was crushed.',
                [
                    'mood' => new Question(
                        name: 'mood',
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
        } catch (JevException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $answer = $result->get('mood');
        $io->success(sprintf(
            'Jev answered in %d ms: mood=%s, confidence %.2f (model %s, %d input tokens, $%.6f).',
            (int)$result->durationMs,
            Cast::string($answer?->choice),
            $answer !== null ? $answer->confidence : 0.0,
            $result->model,
            $result->usage->inputTokens,
            $result->usage->costInUsd(),
        ));

        return Command::SUCCESS;
    }
}
