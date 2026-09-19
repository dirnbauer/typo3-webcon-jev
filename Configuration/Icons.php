<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

$icons = [
    'webcon-jev-module' => 'module-jev.svg',
    'tx_webconjev_decision' => 'tx_webconjev_decision.svg',
    'tx_webconjev_question' => 'tx_webconjev_question.svg',
    'tx_webconjev_criterion' => 'tx_webconjev_criterion.svg',
    'tx_webconjev_run' => 'tx_webconjev_run.svg',
];

return array_map(
    static fn(string $file): array => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:webcon_jev/Resources/Public/Icons/' . $file,
    ],
    $icons,
);
