<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function ($packageKey) {
    if (file_exists(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'))) {
        require_once(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'));
    }

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Teddytrombone']['IdeCompanion']['writerConfiguration'] = [
        // Configuration for WARNING severity, including all
        // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
        \Psr\Log\LogLevel::ERROR => [
            // Add a SyslogWriter
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFile' => \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log/ide_companion.log'
            ],
        ],
    ];
}, 'ide_companion');
