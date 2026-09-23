<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Backend\Controller\ConnectionController;
use Webconsulting\WebconJev\Backend\Controller\DecisionsController;
use Webconsulting\WebconJev\Backend\Controller\RunLogController;

/**
 * Admin > Jev decisions, with three pages that the DocHeader's module menu switches between.
 *
 * The group itself has no page of its own: opening it lands on the page used last. "tools_webconjev"
 * was the module's identifier before 0.2.0, so bookmarks made then still arrive here.
 */
return [
    'webcon_jev' => [
        'parent' => 'admin',
        'position' => ['after' => '*'],
        'access' => 'admin',
        // The decision tables are not workspace-aware; they are edited live or not at all.
        'workspaces' => 'live',
        'path' => '/module/webcon-jev',
        'iconIdentifier' => 'webcon-jev-module',
        'labels' => 'webcon_jev.modules.jev',
        'aliases' => ['tools_webconjev'],
        'appearance' => [
            'dependsOnSubmodules' => true,
        ],
    ],
    DecisionsController::MODULE => [
        'parent' => 'webcon_jev',
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/webcon-jev/decisions',
        'iconIdentifier' => 'tx_webconjev_decision',
        'labels' => 'webcon_jev.modules.decisions',
        'routes' => [
            '_default' => [
                'target' => DecisionsController::class . '::listAction',
            ],
            'edit' => [
                'target' => DecisionsController::class . '::editAction',
            ],
        ],
    ],
    RunLogController::MODULE => [
        'parent' => 'webcon_jev',
        'position' => ['after' => DecisionsController::MODULE],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/webcon-jev/runs',
        'iconIdentifier' => 'tx_webconjev_run',
        'labels' => 'webcon_jev.modules.runs',
        'routes' => [
            '_default' => [
                'target' => RunLogController::class . '::listAction',
            ],
        ],
    ],
    ConnectionController::MODULE => [
        'parent' => 'webcon_jev',
        'position' => ['after' => RunLogController::MODULE],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/webcon-jev/connection',
        'iconIdentifier' => 'webcon-jev-connection',
        'labels' => 'webcon_jev.modules.connection',
        'routes' => [
            '_default' => [
                'target' => ConnectionController::class . '::showAction',
            ],
        ],
    ],
];
