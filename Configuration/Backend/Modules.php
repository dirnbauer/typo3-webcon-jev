<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Backend\Controller\JevModuleController;

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
