<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Powermail\JevOperator;

defined('TYPO3') or die();

if (!isset($GLOBALS['TCA']['tx_powermailcond_domain_model_rule'])) {
    return;
}

$ll = 'LLL:EXT:webcon_jev/Resources/Private/Language/locallang_db.xlf:';
$choiceOperators = implode(',', JevOperator::choiceValues());
$numericOperators = implode(',', JevOperator::numericValues());
$allOperators = implode(',', JevOperator::values());

$GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['columns']['ops']['config']['items'] = array_merge(
    $GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['columns']['ops']['config']['items'],
    [
        ['label' => $ll . 'rule.operator.group', 'value' => '--div--'],
        ['label' => $ll . 'rule.operator.choiceIs', 'value' => JevOperator::ChoiceIs->value],
        ['label' => $ll . 'rule.operator.choiceIsNot', 'value' => JevOperator::ChoiceIsNot->value],
        ['label' => $ll . 'rule.operator.scoreAtLeast', 'value' => JevOperator::ScoreAtLeast->value],
        ['label' => $ll . 'rule.operator.scoreBelow', 'value' => JevOperator::ScoreBelow->value],
        ['label' => $ll . 'rule.operator.noulAbove', 'value' => JevOperator::NoulAbove->value],
        ['label' => $ll . 'rule.operator.noulBelow', 'value' => JevOperator::NoulBelow->value],
    ],
);

$GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['columns'] += [
    'tx_webconjev_decision' => [
        'exclude' => true,
        'label' => $ll . 'rule.decision',
        'description' => $ll . 'rule.decision.description',
        'displayCond' => 'FIELD:ops:IN:' . $allOperators,
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [['label' => '', 'value' => 0]],
            'foreign_table' => 'tx_webconjev_decision',
            'foreign_table_where' => 'AND {#tx_webconjev_decision}.{#sys_language_uid} IN (-1,0) ORDER BY {#tx_webconjev_decision}.{#title}',
            'size' => 1,
            'maxitems' => 1,
            'default' => 0,
        ],
    ],
    'tx_webconjev_question' => [
        'exclude' => true,
        'label' => $ll . 'rule.question',
        'description' => $ll . 'rule.question.description',
        'displayCond' => 'FIELD:ops:IN:' . $allOperators,
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [['label' => '', 'value' => '']],
            'itemsProcFunc' => \Webconsulting\WebconJev\Backend\ItemsProc\QuestionItems::class . '->forRule',
            'size' => 1,
            'maxitems' => 1,
            'default' => '',
        ],
    ],
    'tx_webconjev_expect' => [
        'exclude' => true,
        'label' => $ll . 'rule.expect',
        'description' => $ll . 'rule.expect.description',
        'displayCond' => 'FIELD:ops:IN:' . $choiceOperators,
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [['label' => '', 'value' => '']],
            'itemsProcFunc' => \Webconsulting\WebconJev\Backend\ItemsProc\QuestionItems::class . '->outcomesForRule',
            'size' => 1,
            'maxitems' => 1,
            'default' => '',
        ],
    ],
    'tx_webconjev_threshold' => [
        'exclude' => true,
        'label' => $ll . 'rule.threshold',
        'description' => $ll . 'rule.threshold.description',
        'displayCond' => 'FIELD:ops:IN:' . $numericOperators,
        'config' => [
            'type' => 'number',
            'format' => 'decimal',
            'size' => 10,
            'default' => 0.6,
        ],
    ],
];

$GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['types']['1']['showitem'] = str_replace(
    'equal_field,',
    'equal_field, tx_webconjev_decision, tx_webconjev_question, tx_webconjev_expect, tx_webconjev_threshold,',
    $GLOBALS['TCA']['tx_powermailcond_domain_model_rule']['types']['1']['showitem'],
);
