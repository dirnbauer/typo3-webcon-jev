<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_powermail_domain_model_form'])) {
    return;
}

$ll = 'LLL:EXT:webcon_jev/Resources/Private/Language/locallang_db.xlf:';

$GLOBALS['TCA']['tx_powermail_domain_model_form']['columns'] += [
    'tx_webconjev_routing_decision' => [
        'exclude' => true,
        'label' => $ll . 'form.routingDecision',
        'description' => $ll . 'form.routingDecision.description',
        'l10n_mode' => 'exclude',
        'l10n_display' => 'defaultAsReadonly',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [['label' => $ll . 'form.routingDecision.none', 'value' => 0]],
            'foreign_table' => 'tx_webconjev_decision',
            'foreign_table_where' => 'AND {#tx_webconjev_decision}.{#sys_language_uid} IN (-1,0) ORDER BY {#tx_webconjev_decision}.{#title}',
            'size' => 1,
            'maxitems' => 1,
            'default' => 0,
            'onChange' => 'reload',
        ],
    ],
    'tx_webconjev_routing_question' => [
        'exclude' => true,
        'label' => $ll . 'form.routingQuestion',
        'description' => $ll . 'form.routingQuestion.description',
        'l10n_mode' => 'exclude',
        'l10n_display' => 'defaultAsReadonly',
        'displayCond' => 'FIELD:tx_webconjev_routing_decision:>:0',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [['label' => '', 'value' => '']],
            'itemsProcFunc' => \Webconsulting\WebconJev\Backend\ItemsProc\QuestionItems::class . '->forForm',
            'size' => 1,
            'maxitems' => 1,
            'default' => '',
        ],
    ],
];

ExtensionManagementUtility::addToAllTCAtypes(
    'tx_powermail_domain_model_form',
    '--div--;' . $ll . 'form.tab.jev, tx_webconjev_routing_decision, tx_webconjev_routing_question',
);
