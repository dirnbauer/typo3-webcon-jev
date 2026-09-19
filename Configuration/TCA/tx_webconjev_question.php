<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'locallang_db.xlfx_webconjev_question',
        'label' => 'name',
        'label_alt' => 'instructions',
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
        'iconfile' => 'EXT:webcon_jev/Resources/Public/Icons/tx_webconjev_question.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => 'name, type, instructions, criteria, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, --palette--;;language',
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
                'allowed' => 'tx_webconjev_question',
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
        'decision' => ['config' => ['type' => 'passthrough']],
        'name' => [
            'label' => 'locallang_db.xlfx_webconjev_question.name',
            'description' => 'locallang_db.xlfx_webconjev_question.name.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => ['type' => 'input', 'size' => 25, 'eval' => 'trim,alphanum_x', 'required' => true],
        ],
        'type' => [
            'label' => 'locallang_db.xlfx_webconjev_question.type',
            'description' => 'locallang_db.xlfx_webconjev_question.type.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'locallang_db.xlfx_webconjev_question.type.choice', 'value' => 'choice'],
                    ['label' => 'locallang_db.xlfx_webconjev_question.type.score', 'value' => 'score'],
                    ['label' => 'locallang_db.xlfx_webconjev_question.type.noul', 'value' => 'noul'],
                ],
                'default' => 'choice',
            ],
        ],
        'instructions' => [
            'label' => 'locallang_db.xlfx_webconjev_question.instructions',
            'description' => 'locallang_db.xlfx_webconjev_question.instructions.description',
            'config' => ['type' => 'text', 'cols' => 60, 'rows' => 3, 'required' => true],
        ],
        'criteria' => [
            'label' => 'locallang_db.xlfx_webconjev_question.criteria',
            'description' => 'locallang_db.xlfx_webconjev_question.criteria.description',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_webconjev_criterion',
                'foreign_field' => 'question',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => false,
                    'useSortable' => true,
                    'showSynchronizationLink' => true,
                    'showAllLocalizationLink' => true,
                    'showPossibleLocalizationRecords' => true,
                ],
            ],
        ],
    ],
];
