<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_powermail_domain_model_mail'])) {
    return;
}

$ll = 'webcon_jev.db:';

$GLOBALS['TCA']['tx_powermail_domain_model_mail']['columns']['tx_webconjev_routing_summary'] = [
    'exclude' => true,
    'label' => $ll . 'mail.routingSummary',
    'description' => $ll . 'mail.routingSummary.description',
    'config' => [
        'type' => 'input',
        'size' => 50,
        'readOnly' => true,
        // Bookkeeping about how a mail was routed, not something to find mails by. TYPO3 v14
        // searches every input column unless it says otherwise.
        'searchable' => false,
    ],
];

ExtensionManagementUtility::addToAllTCAtypes(
    'tx_powermail_domain_model_mail',
    'tx_webconjev_routing_summary',
);
