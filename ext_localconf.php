<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function ($packageKey) {
    if (file_exists(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'))) {
        require_once(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'));
    }
}, 'ide_companion');
