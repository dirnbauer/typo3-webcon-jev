<?php

declare(strict_types=1);

use Webconsulting\WebconJev\Middleware\DebugPanelMiddleware;

return [
    'frontend' => [
        // Inside the TypoScript preparation, so the debug switch can be read from the setup.
        'webconsulting/webcon-jev/debug-panel' => [
            'target' => DebugPanelMiddleware::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
            'before' => [
                'typo3/cms-frontend/shortcut-and-mountpoint-redirect',
            ],
        ],
    ],
];
