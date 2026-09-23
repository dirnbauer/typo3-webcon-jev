<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Backend\Controller\ConnectionApiController;
use Webconsulting\WebconJev\Backend\Controller\DecisionApiController;
use Webconsulting\WebconJev\Backend\Controller\DecisionsController;
use Webconsulting\WebconJev\Backend\Controller\PlaygroundApiController;

/**
 * The module's endpoints. Each inherits the module's access, so a backend user who cannot open
 * Jev decisions cannot save one, delete one, or spend money in the playground either.
 */
return [
    'webcon_jev_decision_save' => [
        'path' => '/webcon-jev/decision/save',
        'target' => DecisionApiController::class . '::saveAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => DecisionsController::MODULE,
    ],
    'webcon_jev_decision_delete' => [
        'path' => '/webcon-jev/decision/delete',
        'target' => DecisionApiController::class . '::deleteAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => DecisionsController::MODULE,
    ],
    'webcon_jev_playground' => [
        'path' => '/webcon-jev/playground',
        'target' => PlaygroundApiController::class . '::runAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => DecisionsController::MODULE,
    ],
    'webcon_jev_ping' => [
        'path' => '/webcon-jev/ping',
        'target' => ConnectionApiController::class . '::pingAction',
        'methods' => ['POST'],
        'inheritAccessFromModule' => DecisionsController::MODULE,
    ],
];
