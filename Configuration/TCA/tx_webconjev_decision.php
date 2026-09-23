<?php

declare(strict_types=1);

/*
 * The language, translation-parent and visibility columns are not declared here: TYPO3 v14 adds
 * them from the ctrl section, with the core's own labels.
 */
return [
    'ctrl' => [
        'title' => 'webcon_jev.db:tx_webconjev_decision',
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
                --div--;core.form.tabs:general,
                    title, identifier, description, questions,
                --div--;webcon_jev.db:tx_webconjev_decision.tab.state,
                    state_template,
                --div--;webcon_jev.db:tx_webconjev_decision.tab.behaviour,
                    confidence_threshold, default_outcome, model, cache_lifetime,
                --div--;core.form.tabs:language,
                    --palette--;;language,
                --div--;core.form.tabs:access,
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
        'title' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.title',
            'config' => ['type' => 'input', 'size' => 40, 'eval' => 'trim', 'required' => true],
        ],
        'identifier' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.identifier',
            'description' => 'webcon_jev.db:tx_webconjev_decision.identifier.description',
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
                // Unique across the table, not per site: code looks a decision up by identifier
                // alone, so two sites sharing one would get whichever row the database returned.
                'eval' => 'unique',
                'appearance' => ['prefix' => ''],
            ],
        ],
        'description' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.description',
            'config' => ['type' => 'text', 'cols' => 40, 'rows' => 3],
        ],
        'state_template' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.stateTemplate',
            'description' => 'webcon_jev.db:tx_webconjev_decision.stateTemplate.description',
            'config' => ['type' => 'text', 'cols' => 60, 'rows' => 8],
        ],
        'model' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.model',
            'description' => 'webcon_jev.db:tx_webconjev_decision.model.description',
            'l10n_mode' => 'exclude',
            'config' => ['type' => 'input', 'size' => 20, 'eval' => 'trim'],
        ],
        'confidence_threshold' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.confidenceThreshold',
            'description' => 'webcon_jev.db:tx_webconjev_decision.confidenceThreshold.description',
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
            'label' => 'webcon_jev.db:tx_webconjev_decision.defaultOutcome',
            'description' => 'webcon_jev.db:tx_webconjev_decision.defaultOutcome.description',
            'config' => ['type' => 'input', 'size' => 30, 'eval' => 'trim'],
        ],
        'cache_lifetime' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.cacheLifetime',
            'description' => 'webcon_jev.db:tx_webconjev_decision.cacheLifetime.description',
            'l10n_mode' => 'exclude',
            'config' => ['type' => 'number', 'size' => 10, 'default' => -1, 'range' => ['lower' => -1]],
        ],
        'questions' => [
            'label' => 'webcon_jev.db:tx_webconjev_decision.questions',
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
