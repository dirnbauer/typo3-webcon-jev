<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'webcon_jev.db:tx_webconjev_criterion',
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
            'showitem' => 'identifier, description, outcome_value, --div--;core.form.tabs:language, --palette--;;language',
        ],
    ],
    'palettes' => [
        'language' => ['showitem' => 'sys_language_uid, l10n_parent'],
    ],
    'columns' => [
        'question' => ['config' => ['type' => 'passthrough']],
        'identifier' => [
            'label' => 'webcon_jev.db:tx_webconjev_criterion.identifier',
            'description' => 'webcon_jev.db:tx_webconjev_criterion.identifier.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => ['type' => 'input', 'size' => 25, 'eval' => 'trim,alphanum_x'],
        ],
        'description' => [
            'label' => 'webcon_jev.db:tx_webconjev_criterion.description',
            'description' => 'webcon_jev.db:tx_webconjev_criterion.description.description',
            'config' => ['type' => 'text', 'cols' => 60, 'rows' => 2, 'required' => true],
        ],
        'outcome_value' => [
            'label' => 'webcon_jev.db:tx_webconjev_criterion.outcomeValue',
            'description' => 'webcon_jev.db:tx_webconjev_criterion.outcomeValue.description',
            'config' => ['type' => 'input', 'size' => 40, 'eval' => 'trim'],
        ],
    ],
];
