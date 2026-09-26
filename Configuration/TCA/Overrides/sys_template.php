<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// For sites that still include TypoScript through a template record. Sites on site sets add the
// set "webconsulting/webcon-jev" instead; both load the same setup.
ExtensionManagementUtility::addStaticFile('webcon_jev', 'Configuration/TypoScript', 'Jev decisions');
