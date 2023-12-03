<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function ($packageKey) {
    if (file_exists(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'))) {
        require_once(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'));
    }

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Teddytrombone']['IdeCompanion']['writerConfiguration'] = [
        \Psr\Log\LogLevel::DEBUG => [
            // Add a SyslogWriter
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFile' => \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log/ide_companion.log'
            ],
        ],
        \Psr\Log\LogLevel::NOTICE => [
            // Add a SyslogWriter
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFile' => \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log/ide_companion_notice.log'
            ],
        ],
        \Psr\Log\LogLevel::INFO => [
            // Add a SyslogWriter
            \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                'logFile' => \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log/ide_companion_info.log'
            ],
        ],
    ];
}, 'ide_companion');
