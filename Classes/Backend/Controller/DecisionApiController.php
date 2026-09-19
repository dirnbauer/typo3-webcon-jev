<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Reading and writing decisions from the module.
 *
 * Writes go through the DataHandler rather than straight to the database, so a decision edited
 * here gets the same history entry, the same permission check and the same hooks as one edited in
 * a normal TYPO3 form — and can be rolled back from the record history like anything else.
 */
final readonly class DecisionApiController
{
    private const DECISIONS = 'tx_webconjev_decision';
    private const QUESTIONS = 'tx_webconjev_question';
    private const CRITERIA = 'tx_webconjev_criterion';

    public function __construct(
        private DecisionRepository $decisions,
        private Settings $settings,
    ) {}

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $language = Cast::int($request->getQueryParams()['language'] ?? null);

        return new JsonResponse([
            'decisions' => array_map(
                static fn(object $decision): array => $decision->toArray(),
                $this->decisions->findAll($language, true),
            ),
        ]);
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $payload = $this->payload($request);
        if (!is_array($payload['decision'] ?? null)) {
            return new JsonResponse(['ok' => false, 'error' => 'No decision in the request.'], 400);
        }

        $decision = Cast::map($payload['decision']);
        $uid = Cast::int($decision['uid'] ?? null);
        $decisionId = $uid > 0 ? (string)$uid : StringUtility::getUniqueId('NEW');
        $pid = $uid > 0 ? null : $this->settings->storagePid();

        $questionIds = [];
        $data = [self::DECISIONS => [], self::QUESTIONS => [], self::CRITERIA => []];

        foreach (Cast::array($decision['questions'] ?? null) as $index => $rawQuestion) {
            $question = Cast::map($rawQuestion);
            if ($question === []) {
                continue;
            }
            $questionUid = Cast::int($question['uid'] ?? null);
            $questionId = $questionUid > 0 ? (string)$questionUid : StringUtility::getUniqueId('NEW');
            $questionIds[] = $questionId;

            $criterionIds = [];
            foreach (Cast::array($question['criteria'] ?? null) as $criterionIndex => $rawCriterion) {
                $criterion = Cast::map($rawCriterion);
                if ($criterion === []) {
                    continue;
                }
                $criterionUid = Cast::int($criterion['uid'] ?? null);
                $criterionId = $criterionUid > 0 ? (string)$criterionUid : StringUtility::getUniqueId('NEW');
                $criterionIds[] = $criterionId;

                $data[self::CRITERIA][$criterionId] = array_filter([
                    'pid' => $criterionUid > 0 ? null : $pid,
                    'question' => $questionId,
                    'sorting' => (Cast::int($criterionIndex) + 1) * 64,
                    'identifier' => Cast::string($criterion['identifier'] ?? null),
                    'description' => Cast::string($criterion['description'] ?? null),
                    'outcome_value' => Cast::string($criterion['outcomeValue'] ?? null),
                ], static fn(mixed $v): bool => $v !== null);
            }

            $data[self::QUESTIONS][$questionId] = array_filter([
                'pid' => $questionUid > 0 ? null : $pid,
                'decision' => $decisionId,
                'sorting' => (Cast::int($index) + 1) * 64,
                'name' => Cast::string($question['name'] ?? null),
                'type' => Cast::trimmed($question['type'] ?? null, 'choice'),
                'instructions' => Cast::string($question['instructions'] ?? null),
                'criteria' => implode(',', $criterionIds),
            ], static fn(mixed $v): bool => $v !== null);
        }

        $data[self::DECISIONS][$decisionId] = array_filter([
            'pid' => $pid,
            'title' => Cast::string($decision['title'] ?? null),
            'identifier' => Cast::string($decision['identifier'] ?? null),
            'description' => Cast::string($decision['description'] ?? null),
            'state_template' => Cast::string($decision['stateTemplate'] ?? null),
            'model' => Cast::string($decision['model'] ?? null),
            'confidence_threshold' => Cast::float($decision['confidenceThreshold'] ?? null, 0.6),
            'cache_lifetime' => Cast::int($decision['cacheLifetime'] ?? null, -1),
            'default_outcome' => Cast::string($decision['defaultOutcome'] ?? null),
            'questions' => implode(',', $questionIds),
        ], static fn(mixed $v): bool => $v !== null);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            return new JsonResponse(['ok' => false, 'errors' => $dataHandler->errorLog], 422);
        }

        $savedUid = Cast::int($dataHandler->substNEWwithIDs[$decisionId] ?? null, $uid);
        $saved = $this->decisions->findByUid($savedUid, 0, true);

        return new JsonResponse([
            'ok' => true,
            'decision' => $saved?->toArray(),
        ]);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $uid = Cast::int($this->payload($request)['uid'] ?? null);
        if ($uid <= 0) {
            return new JsonResponse(['ok' => false, 'error' => 'No decision to delete.'], 400);
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [self::DECISIONS => [$uid => ['delete' => 1]]]);
        $dataHandler->process_cmdmap();

        if ($dataHandler->errorLog !== []) {
            return new JsonResponse(['ok' => false, 'errors' => $dataHandler->errorLog], 422);
        }

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ServerRequestInterface $request): array
    {
        $body = (string)$request->getBody();
        $decoded = Cast::map($body !== '' ? json_decode($body, true) : null);

        return $decoded !== [] ? $decoded : Cast::map($request->getParsedBody());
    }
}
