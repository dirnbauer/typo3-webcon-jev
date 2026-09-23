<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'webcon_jev.db:tx_webconjev_question',
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
            'showitem' => 'name, type, instructions, criteria, --div--;core.form.tabs:language, --palette--;;language',
        ],
    ],
    'palettes' => [
        'language' => ['showitem' => 'sys_language_uid, l10n_parent'],
    ],
    'columns' => [
        'decision' => ['config' => ['type' => 'passthrough']],
        'name' => [
            'label' => 'webcon_jev.db:tx_webconjev_question.name',
            'description' => 'webcon_jev.db:tx_webconjev_question.name.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => ['type' => 'input', 'size' => 25, 'eval' => 'trim,alphanum_x', 'required' => true],
        ],
        'type' => [
            'label' => 'webcon_jev.db:tx_webconjev_question.type',
            'description' => 'webcon_jev.db:tx_webconjev_question.type.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'onChange' => 'reload',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'webcon_jev.db:tx_webconjev_question.type.choice', 'value' => 'choice'],
                    ['label' => 'webcon_jev.db:tx_webconjev_question.type.score', 'value' => 'score'],
                    ['label' => 'webcon_jev.db:tx_webconjev_question.type.noul', 'value' => 'noul'],
                ],
                'default' => 'choice',
            ],
        ],
        'instructions' => [
            'label' => 'webcon_jev.db:tx_webconjev_question.instructions',
            'description' => 'webcon_jev.db:tx_webconjev_question.instructions.description',
            'config' => ['type' => 'text', 'cols' => 60, 'rows' => 3, 'required' => true],
        ],
        'criteria' => [
            'label' => 'webcon_jev.db:tx_webconjev_question.criteria',
            'description' => 'webcon_jev.db:tx_webconjev_question.criteria.description',
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
