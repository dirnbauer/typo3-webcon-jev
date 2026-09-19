<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_powermail_domain_model_mail'])) {
    return;
}

$ll = 'LLL:EXT:webcon_jev/Resources/Private/Language/locallang_db.xlf:';

$GLOBALS['TCA']['tx_powermail_domain_model_mail']['columns']['tx_webconjev_routing_summary'] = [
    'exclude' => true,
    'label' => $ll . 'mail.routingSummary',
    'description' => $ll . 'mail.routingSummary.description',
    'config' => [
        'type' => 'input',
        'size' => 50,
        'readOnly' => true,
    ],
];

ExtensionManagementUtility::addToAllTCAtypes(
    'tx_powermail_domain_model_mail',
    'tx_webconjev_routing_summary',
);
