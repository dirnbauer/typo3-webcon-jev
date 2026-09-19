<?php

declare(strict_types=1);

if (!\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('shadcn_ui')) {
    return [];
}

return [
    'dependencies' => ['backend', 'shadcn_ui'],
    'imports' => [
        '@webconsulting/webcon-jev/' => 'EXT:webcon_jev/Resources/Public/JavaScript/',
    ],
];
