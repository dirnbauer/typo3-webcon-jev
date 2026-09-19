<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die();

// Answers live here, keyed by the decision and the exact state they were given. A visitor
// correcting a typo and changing it back asks the same question twice; the second one is free.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['webcon_jev'] ??= [
    'frontend' => VariableFrontend::class,
    'backend' => Typo3DatabaseBackend::class,
    'options' => ['defaultLifetime' => 300],
    'groups' => ['system'],
];
