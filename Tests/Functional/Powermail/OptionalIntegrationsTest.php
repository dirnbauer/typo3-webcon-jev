<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Powermail;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Schema\SearchableSchemaFieldsCollector;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\WebconJev\Editing\DecisionUsage;
use Webconsulting\WebconJev\Powermail\JevRuleListener;
use Webconsulting\WebconJev\Powermail\MailRoutingListener;

/**
 * The other half of the boot proof: with the optional extensions present, the guarded
 * registrations must actually appear — or the guards are hiding the integration rather than
 * protecting the boot.
 */
final class OptionalIntegrationsTest extends FunctionalTestCase
{
    // powermail declares scheduler as a hard dependency; the package manager refuses to load
    // it otherwise, and the test then reports a cascade of unrelated container errors.
    protected array $coreExtensionsToLoad = ['backend', 'install', 'frontend', 'extbase', 'fluid', 'scheduler'];

    protected array $testExtensionsToLoad = ['nr_vault', 'powermail', 'powermail_cond', 'webcon_jev'];

    #[Test]
    public function theConditionOperatorAndTheRouterAreListening(): void
    {
        $listeners = $this->registeredListeners();

        self::assertContains(
            [\In2code\PowermailCond\Event\EvaluateRuleEvent::class, JevRuleListener::class, '__invoke'],
            $listeners,
        );
        self::assertContains(
            [\In2code\Powermail\Events\FormControllerCreateActionAfterMailDbSavedEvent::class, MailRoutingListener::class, 'decide'],
            $listeners,
        );
        self::assertContains(
            [\In2code\Powermail\Events\ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent::class, MailRoutingListener::class, 'applyReceivers'],
            $listeners,
        );
    }

    #[Test]
    public function theRuleOperatorsAndRoutingFieldsAreInTheTca(): void
    {
        $operators = array_column($GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['columns']['ops']['config']['items'], 'value');

        self::assertContains(100, $operators, 'Jev chose');
        self::assertContains(105, $operators, 'Jev says yes, below');
        self::assertArrayHasKey('tx_webconjev_decision', $GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['columns']);
        self::assertArrayHasKey('tx_webconjev_routing_decision', $GLOBALS['TCA']['tx_powermail_domain_model_form']['columns']);
        self::assertArrayHasKey('tx_webconjev_routing_summary', $GLOBALS['TCA']['tx_powermail_domain_model_mail']['columns']);
    }

    #[Test]
    public function noColumnThisExtensionAddsToPowermailIsSearchedInTheBackend(): void
    {
        // TYPO3 v14 searches every text-like column unless it says otherwise. The routing summary
        // is bookkeeping about a mail, not something to find mails by; the other columns are
        // selects and numbers, which are never searched — this keeps it that way if one changes.
        $searched = [];
        foreach (['tx_powermail_domain_model_form', 'tx_powermail_domain_model_mail', 'tx_powermailcond_domain_model_rule'] as $table) {
            $schema = $this->get(TcaSchemaFactory::class)->get($table);
            foreach ($schema->getFields() as $field) {
                if (str_starts_with($field->getName(), 'tx_webconjev_') && $field->isSearchable()) {
                    $searched[] = $table . '.' . $field->getName();
                }
            }
        }

        self::assertSame([], $searched);
        self::assertNotContains(
            'tx_webconjev_routing_summary',
            $this->get(SearchableSchemaFieldsCollector::class)->getFieldNames('tx_powermail_domain_model_mail'),
        );
    }

    #[Test]
    public function theModuleCountsTheFormsAndConditionRulesUsingADecision(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/usage.csv');

        self::assertSame(
            [1 => ['forms' => 2, 'rules' => 1], 2 => ['forms' => 0, 'rules' => 0]],
            $this->get(DecisionUsage::class)->countFor([1, 2]),
            'a hidden form still routes through it, a deleted rule no longer reads it, and a translated form or rule is the same one',
        );
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function registeredListeners(): array
    {
        $registered = [];
        foreach ($this->get(ListenerProvider::class)->getAllListenerDefinitions() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $registered[] = [(string)$event, (string)($listener['service'] ?? ''), (string)($listener['method'] ?? '')];
            }
        }

        return $registered;
    }
}
