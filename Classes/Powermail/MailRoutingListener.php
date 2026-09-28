<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Mail;
use In2code\Powermail\Events\FormControllerCreateActionAfterMailDbSavedEvent;
use In2code\Powermail\Events\FormControllerCreateActionBeforeRenderViewEvent;
use In2code\Powermail\Events\MailRepositoryGetVariablesWithMarkersFromMailEvent;
use In2code\Powermail\Events\ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent;
use In2code\Powermail\Utility\ConfigurationUtility;
use In2code\Powermail\Utility\TypoScriptUtility;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\WebconJev\Debug\DebugLog;
use Webconsulting\WebconJev\Debug\DecisionTrace;
use Webconsulting\WebconJev\Debug\RoutingTrace;
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
 * decision's confidence threshold, the decision's default outcome gets the mail; with no default
 * outcome nothing is replaced and the form's own receiver gets it, exactly as it would without
 * this extension. Powermail's own receiver overrides always win.
 */
final readonly class MailRoutingListener
{
    private const string FORM_TABLE = 'tx_powermail_domain_model_form';

    private const string LABELS = 'LLL:EXT:webcon_jev/Resources/Private/Language/frontend.xlf';

    public function __construct(
        private DecisionRepository $decisions,
        private DecisionRunner $runner,
        private MailStateCollector $stateCollector,
        private RoutingDecisionStore $store,
        private ConnectionPool $connectionPool,
        private LoggerInterface $logger,
        private DebugLog $debugLog,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * Decide, while the submission is fresh and the mail has not been addressed yet.
     */
    public function decide(FormControllerCreateActionAfterMailDbSavedEvent $event): void
    {
        $this->decideFor($event->getMail());
    }

    /**
     * With double opt-in the receiver mail goes out when the visitor confirms, in a later request:
     * what the submission's request decided is gone by then. So decide again, from the saved mail.
     * For the same text the answer comes from the decision cache, while that holds it.
     */
    public function decideOnConfirmation(FormControllerCreateActionBeforeRenderViewEvent $event): void
    {
        // powermail forwards a saved mail with a wrong hash to the form before this event.
        if ($event->getHash() === '' || $event->getMail()->getUid() === null) {
            return;
        }

        $this->decideFor($event->getMail());
    }

    private function decideFor(Mail $mail): void
    {
        $this->store->forget();

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
            $this->debugLog->note(sprintf(
                'Form %d routes through decision %d, which is gone; its own receiver got the mail.',
                $formUid,
                $decisionUid,
            ));

            return;
        }

        $context = $this->stateCollector->collect($mail);
        $outcome = $this->runner->run(
            $decision,
            $context,
            RunLogger::CONTEXT_FINISHER,
            sprintf('form %d, mail %d', $formUid, Cast::int($mail->getUid())),
        );

        $outcomeValue = $outcome->outcomeFor($questionName);
        $receivers = $this->receiversFrom($outcomeValue);

        $this->debugLog->trace(
            DecisionTrace::ROUTING . ':' . $formUid,
            new DecisionTrace(DecisionTrace::ROUTING, $outcome, $context),
        )->setRouting(new RoutingTrace(
            question: $questionName,
            outcomeValue: $outcomeValue,
            receivers: $receivers,
            usedDefault: $outcome->usedDefaultFor($questionName),
        ));

        $summary = $outcome->routingSummary($questionName, $receivers);
        $this->rememberOnMail(Cast::int($mail->getUid()), $summary);
        if ($receivers === []) {
            return;
        }

        $this->store->remember(new RoutingDecision($outcome, $questionName, $receivers, Cast::int($mail->getUid()), $summary));
    }

    /**
     * Apply what was decided, where powermail builds the receiver list.
     */
    public function applyReceivers(ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent $event): void
    {
        $decision = $this->store->decision();
        if ($decision === null) {
            return;
        }

        $override = $this->powermailOverride();
        if ($override !== null) {
            $this->debugLog->note(sprintf('Powermail\'s %s addressed this mail; Jev\'s answer was not applied.', $override));
            $this->rememberOnMail($decision->mailUid, sprintf('%s (not applied: powermail\'s %s addressed the mail)', $decision->summary, $override));

            return;
        }

        $event->setEmailArray($decision->receivers);
    }

    /**
     * {jev_routing} for the thank-you text and the mails: where Jev sent the submission and how
     * sure it was, in the visitor's language. Empty for a mail routing did not address.
     */
    public function provideVariables(MailRepositoryGetVariablesWithMarkersFromMailEvent $event): void
    {
        $decision = $this->store->decision();
        if ($decision === null || $decision->mailUid !== Cast::int($event->getMail()->getUid())) {
            return;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $language = $request instanceof ServerRequestInterface ? $request->getAttribute('language') : null;
        if (!$language instanceof SiteLanguage) {
            return;
        }

        $sentence = $this->languageServiceFactory->createFromSiteLanguage($language)->sL(self::LABELS . ':' . $decision->messageKey());
        $variables = $event->getVariables();
        $variables['jev_routing'] = sprintf($sentence, implode(', ', $decision->receivers), $decision->confidence((string)$language->getLocale()));
        $event->setVariables($variables);
    }

    /**
     * Powermail's own receiver overrides win. Both exist to keep mail away from the real receivers:
     * the development-context address sends every mail of a development system to one person, and
     * a TypoScript receiver.overwrite.email does the same for a site or a page. Powermail applies
     * them before this event, so replacing the list here would send a staging system's test
     * submissions to the departments after all.
     */
    private function powermailOverride(): ?string
    {
        if (ConfigurationUtility::getDevelopmentContextEmail() !== '') {
            return 'development-context address';
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $typoScript = $request instanceof ServerRequestInterface ? $request->getAttribute('frontend.typoscript') : null;
        if (!$typoScript instanceof FrontendTypoScript) {
            return null;
        }
        try {
            $setup = $typoScript->getSetupArray();
        } catch (RuntimeException) {
            return null;
        }

        $overwrite = $setup;
        foreach (['plugin.', 'tx_powermail.', 'settings.', 'setup.', 'receiver.', 'overwrite.'] as $segment) {
            $overwrite = Cast::map($overwrite[$segment] ?? null);
        }
        if (!isset($overwrite['email']) && !isset($overwrite['email.'])) {
            return null;
        }

        // Evaluated the way powermail evaluates it, split the way powermail splits it.
        $addresses = preg_split('/[\s,;|]+/', TypoScriptUtility::overwriteValueFromTypoScript('', $overwrite, 'email')) ?: [];
        foreach ($addresses as $address) {
            if (GeneralUtility::validEmail($address)) {
                return 'receiver.overwrite.email';
            }
        }

        return null;
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
    private function rememberOnMail(int $uid, string $summary): void
    {
        if ($uid <= 0) {
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
