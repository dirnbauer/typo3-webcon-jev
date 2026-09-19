<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Backend\Controller\DecisionApiController;
use Webconsulting\WebconJev\Backend\Controller\PlaygroundApiController;
use Webconsulting\WebconJev\Backend\Controller\StatusApiController;

return [
    'webcon_jev_status' => [
        'path' => '/webcon-jev/status',
        'target' => StatusApiController::class . '::status',
    ],
    'webcon_jev_ping' => [
        'path' => '/webcon-jev/ping',
        'target' => StatusApiController::class . '::ping',
        'methods' => ['POST'],
    ],
    'webcon_jev_runs' => [
        'path' => '/webcon-jev/runs',
        'target' => StatusApiController::class . '::runs',
    ],
    'webcon_jev_decisions' => [
        'path' => '/webcon-jev/decisions',
        'target' => DecisionApiController::class . '::list',
    ],
    'webcon_jev_decision_save' => [
        'path' => '/webcon-jev/decision/save',
        'target' => DecisionApiController::class . '::save',
        'methods' => ['POST'],
    ],
    'webcon_jev_decision_delete' => [
        'path' => '/webcon-jev/decision/delete',
        'target' => DecisionApiController::class . '::delete',
        'methods' => ['POST'],
    ],
    'webcon_jev_playground' => [
        'path' => '/webcon-jev/playground',
        'target' => PlaygroundApiController::class . '::run',
        'methods' => ['POST'],
    ],
];
