<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'locallang_db.xlfx_webconjev_criterion',
        'label' => 'identifier',
        'label_alt' => 'description',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        'delete' => 'deleted',
        'rootLevel' => -1,
        'sortby' => 'sorting',
        'enablecolumns' => ['disabled' => 'hidden'],
        'hideTable' => true,
        'iconfile' => 'EXT:webcon_jev/Resources/Public/Icons/tx_webconjev_criterion.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => 'identifier, description, outcome_value, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, --palette--;;language',
        ],
    ],
    'palettes' => [
        'language' => ['showitem' => 'sys_language_uid, l10n_parent'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_webconjev_criterion',
                'size' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => ['config' => ['type' => 'passthrough']],
        'l10n_source' => ['config' => ['type' => 'passthrough']],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 0],
        ],
        'question' => ['config' => ['type' => 'passthrough']],
        'identifier' => [
            'label' => 'locallang_db.xlfx_webconjev_criterion.identifier',
            'description' => 'locallang_db.xlfx_webconjev_criterion.identifier.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => ['type' => 'input', 'size' => 25, 'eval' => 'trim,alphanum_x'],
        ],
        'description' => [
            'label' => 'locallang_db.xlfx_webconjev_criterion.description',
            'description' => 'locallang_db.xlfx_webconjev_criterion.description.description',
            'config' => ['type' => 'text', 'cols' => 60, 'rows' => 2, 'required' => true],
        ],
        'outcome_value' => [
            'label' => 'locallang_db.xlfx_webconjev_criterion.outcomeValue',
            'description' => 'locallang_db.xlfx_webconjev_criterion.outcomeValue.description',
            'config' => ['type' => 'input', 'size' => 40, 'eval' => 'trim'],
        ],
    ],
];
