<?php

declare(strict_types=1);

namespace Teddytrombone\IdeCompanion\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phpactor\LanguageServer\LanguageServerBuilder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Psr\Log\NullLogger;
use Teddytrombone\IdeCompanion\Lsp\LanguageServer\FluidLsDispatcherFactory;

class LspCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageKey = 'ide_companion';
        if (file_exists(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'))) {
            require_once(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'));
        }

        LanguageServerBuilder::create(new FluidLsDispatcherFactory($this->logger))
            ->build()
            ->run();
        return 0;
    }
}
