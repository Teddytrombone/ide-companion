<?php

namespace Teddytrombone\IdeCompanion\Utility;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;

class LoggingUtility implements SingletonInterface
{
    /** @var LoggerInterface */
    protected $logger;

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
