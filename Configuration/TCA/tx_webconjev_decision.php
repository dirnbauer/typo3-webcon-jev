<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'locallang_db.xlfx_webconjev_decision',
        'label' => 'title',
        'descriptionColumn' => 'description',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        'delete' => 'deleted',
        'rootLevel' => -1,
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:webcon_jev/Resources/Public/Icons/tx_webconjev_decision.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    title, identifier, description, questions,
                --div--;locallang_db.xlfx_webconjev_decision.tab.state,
                    state_template,
                --div--;locallang_db.xlfx_webconjev_decision.tab.behaviour,
                    confidence_threshold, default_outcome, model, cache_lifetime,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    --palette--;;language,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    hidden,
            ',
        ],
    ],
    'palettes' => [
        'language' => [
            'showitem' => 'sys_language_uid, l10n_parent',
        ],
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
                'allowed' => 'tx_webconjev_decision',
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 0,
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
        'title' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.title',
            'config' => ['type' => 'input', 'size' => 40, 'eval' => 'trim', 'required' => true],
        ],
        'identifier' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.identifier',
            'description' => 'locallang_db.xlfx_webconjev_decision.identifier.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'slug',
                'size' => 30,
                'generatorOptions' => [
                    'fields' => ['title'],
                    'replacements' => ['/' => '_', '-' => '_'],
                ],
                'fallbackCharacter' => '_',
                'eval' => 'uniqueInSite',
                'appearance' => ['prefix' => ''],
            ],
        ],
        'description' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.description',
            'config' => ['type' => 'text', 'cols' => 40, 'rows' => 3],
        ],
        'state_template' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.stateTemplate',
            'description' => 'locallang_db.xlfx_webconjev_decision.stateTemplate.description',
            'config' => ['type' => 'text', 'cols' => 60, 'rows' => 8],
        ],
        'model' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.model',
            'description' => 'locallang_db.xlfx_webconjev_decision.model.description',
            'l10n_mode' => 'exclude',
            'config' => ['type' => 'input', 'size' => 20, 'eval' => 'trim'],
        ],
        'confidence_threshold' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.confidenceThreshold',
            'description' => 'locallang_db.xlfx_webconjev_decision.confidenceThreshold.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => 0.6,
                'range' => ['lower' => 0, 'upper' => 1],
            ],
        ],
        'default_outcome' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.defaultOutcome',
            'description' => 'locallang_db.xlfx_webconjev_decision.defaultOutcome.description',
            'config' => ['type' => 'input', 'size' => 30, 'eval' => 'trim'],
        ],
        'cache_lifetime' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.cacheLifetime',
            'description' => 'locallang_db.xlfx_webconjev_decision.cacheLifetime.description',
            'l10n_mode' => 'exclude',
            'config' => ['type' => 'number', 'size' => 10, 'default' => -1, 'range' => ['lower' => -1]],
        ],
        'questions' => [
            'label' => 'locallang_db.xlfx_webconjev_decision.questions',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_webconjev_question',
                'foreign_field' => 'decision',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => false,
                    'expandSingle' => true,
                    'useSortable' => true,
                    'showSynchronizationLink' => true,
                    'showAllLocalizationLink' => true,
                    'showPossibleLocalizationRecords' => true,
                ],
            ],
        ],
    ],
];
