<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webconsulting\WebconJev\Powermail\JevRuleListener;
use Webconsulting\WebconJev\Powermail\MailRoutingListener;

/**
 * The two powermail integrations are registered here, not through #[AsEventListener] attributes,
 * because an attribute naming an event class from an extension nobody installed is a fatal error
 * while the container is built — and both integrations are optional.
 *
 * class_exists() is the right guard for these and the wrong one for a SERVICE dependency. It
 * tests autoloadability, and in Composer mode every installed package is autoloadable whether or
 * not TYPO3 has it active. For a listener that only means an inert registration for an event
 * that never fires. For a constructor argument it meant a container that would not compile — see
 * the note on JevModuleController, which takes its renderer as an optional argument instead.
 * ExtensionManagementUtility::isLoaded() is not an option here: bootstrap builds the container
 * before it hands the package manager to that class.
 *
 * Services.php is loaded before Services.yaml, so these are defined rather than looked up, and
 * the resource scan in Services.yaml excludes them to avoid defining them a second time.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    if (class_exists(\In2code\PowermailCond\Event\EvaluateRuleEvent::class)) {
        $services->set(JevRuleListener::class)
            ->autowire()
            ->tag('event.listener', [
                'identifier' => 'webcon-jev/powermail-cond/evaluate-rule',
                'event' => \In2code\PowermailCond\Event\EvaluateRuleEvent::class,
                'method' => '__invoke',
            ]);
    }

    if (class_exists(\In2code\Powermail\Events\FormControllerCreateActionAfterMailDbSavedEvent::class)) {
        $services->set(MailRoutingListener::class)
            ->autowire()
            ->tag('event.listener', [
                'identifier' => 'webcon-jev/powermail/decide-receiver',
                'event' => \In2code\Powermail\Events\FormControllerCreateActionAfterMailDbSavedEvent::class,
                'method' => 'decide',
            ])
            ->tag('event.listener', [
                'identifier' => 'webcon-jev/powermail/apply-receiver',
                'event' => \In2code\Powermail\Events\ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent::class,
                'method' => 'applyReceivers',
            ]);
    }
};
