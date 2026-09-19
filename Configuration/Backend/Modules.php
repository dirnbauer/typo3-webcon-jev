<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Backend\Controller\JevModuleController;

// The module is built on shadcn_ui's runtime. Without it there is nothing to render, and a module
// whose controller cannot be constructed would be a backend entry that 500s.
if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('shadcn_ui')) {
    return [];
}

return [
    'tools_webconjev' => [
        'parent' => 'tools',
        'position' => ['after' => '*'],
        'access' => 'admin',
        'path' => '/module/tools/jev-decisions',
        'iconIdentifier' => 'webcon-jev-module',
        'labels' => 'LLL:EXT:webcon_jev/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => JevModuleController::class . '::index',
            ],
        ],
    ],
];
