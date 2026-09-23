<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Powermail;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
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
    public function theModuleCountsTheFormsAndConditionRulesUsingADecision(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/usage.csv');

        self::assertSame(
            [1 => ['forms' => 2, 'rules' => 1], 2 => ['forms' => 0, 'rules' => 0]],
            $this->get(DecisionUsage::class)->countFor([1, 2]),
            'a hidden form still routes through it; a deleted rule no longer reads it',
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
