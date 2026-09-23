<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Mail;
use In2code\Powermail\Events\FormControllerCreateActionAfterMailDbSavedEvent;
use In2code\Powermail\Events\ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Routes a finished submission to the department that should answer it.
 *
 * This is the "finisher" half of the integration, in two parts because powermail decides the
 * receiver and finishes the submission at opposite ends of the same request — a real finisher
 * runs after the mail has already gone out, which is too late to address it.
 *
 * So the decision runs the moment the submission is saved and complete, and the answer is applied
 * where powermail assembles the receiver list. If Jev cannot answer, or answers below the
 * decision's confidence threshold, nothing is replaced and the form's own receiver gets the mail
 * exactly as it would without this extension.
 */
final readonly class MailRoutingListener
{
    private const string FORM_TABLE = 'tx_powermail_domain_model_form';

    public function __construct(
        private DecisionRepository $decisions,
        private DecisionRunner $runner,
        private MailStateCollector $stateCollector,
        private RoutingDecisionStore $store,
        private ConnectionPool $connectionPool,
        private LoggerInterface $logger,
    ) {}

    /**
     * Decide, while the submission is fresh and the mail has not been addressed yet.
     */
    public function decide(FormControllerCreateActionAfterMailDbSavedEvent $event): void
    {
        $this->store->forget();

        $mail = $event->getMail();
        $form = $mail->getForm();
        if (!$form instanceof Form) {
            return;
        }

        $formUid = Cast::int($form->getUid());
        $routing = $this->routingFor($formUid);
        if ($routing === null) {
            return;
        }

        [$decisionUid, $questionName] = $routing;
        $decision = $this->decisions->findByUid($decisionUid, $this->languageId());
        if ($decision === null) {
            $this->logger->warning('A powermail form routes through a decision that is gone.', [
                'form' => $formUid,
                'decision' => $decisionUid,
            ]);

            return;
        }

        $outcome = $this->runner->run(
            $decision,
            $this->stateCollector->collect($mail),
            RunLogger::CONTEXT_FINISHER,
            sprintf('form %d, mail %d', $formUid, Cast::int($mail->getUid())),
        );

        $receivers = $this->receiversFrom($outcome->outcomeFor($questionName));
        if ($receivers === []) {
            return;
        }

        $this->store->remember($outcome, $receivers);
        $this->rememberOnMail($mail, $outcome->summary());
    }

    /**
     * Apply what was decided, where powermail builds the receiver list.
     */
    public function applyReceivers(ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent $event): void
    {
        if (!$this->store->hasReceivers()) {
            return;
        }

        $event->setEmailArray($this->store->receivers());
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private function routingFor(int $formUid): ?array
    {
        if ($formUid <= 0) {
            return null;
        }

        $query = $this->connectionPool->getQueryBuilderForTable(self::FORM_TABLE);
        $query->getRestrictions()->removeAll();

        $row = $query
            ->select('tx_webconjev_routing_decision', 'tx_webconjev_routing_question')
            ->from(self::FORM_TABLE)
            ->where($query->expr()->eq('uid', $query->createNamedParameter($formUid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        $decisionUid = Cast::int($row['tx_webconjev_routing_decision'] ?? null);
        $question = Cast::trimmed($row['tx_webconjev_routing_question'] ?? null);

        return $decisionUid > 0 && $question !== '' ? [$decisionUid, $question] : null;
    }

    /**
     * An outcome value may carry several addresses, one per line or comma separated.
     *
     * @return list<string>
     */
    private function receiversFrom(string $outcomeValue): array
    {
        $candidates = GeneralUtility::trimExplode(',', str_replace(["\r\n", "\n", "\r", ';'], ',', $outcomeValue), true);

        return array_values(array_filter(
            $candidates,
            static fn(string $candidate): bool => GeneralUtility::validEmail($candidate),
        ));
    }

    /**
     * Leave the reasoning on the record, so the mail and the backend both show why it went where
     * it went rather than only where.
     */
    private function rememberOnMail(Mail $mail, string $summary): void
    {
        $uid = $mail->getUid();
        if ($uid === null || $uid <= 0) {
            return;
        }

        $this->connectionPool->getConnectionForTable('tx_powermail_domain_model_mail')->update(
            'tx_powermail_domain_model_mail',
            ['tx_webconjev_routing_summary' => mb_substr($summary, 0, 250)],
            ['uid' => $uid],
        );
    }

    private function languageId(): int
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return 0;
        }
        $language = $request->getAttribute('language');

        return $language instanceof SiteLanguage ? $language->getLanguageId() : 0;
    }
}
