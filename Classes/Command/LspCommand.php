<?php

declare(strict_types=1);

namespace Teddytrombone\IdeCompanion\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Amp\Success;
use Phpactor\LanguageServer\Core\Middleware\RequestHandler;
use Phpactor\LanguageServer\Core\Rpc\Message;
use Phpactor\LanguageServer\Core\Rpc\RequestMessage;
use Phpactor\LanguageServer\Core\Rpc\ResponseMessage;
use Phpactor\LanguageServer\Middleware\ClosureMiddleware;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher\MiddlewareDispatcher;
use Phpactor\LanguageServerProtocol\InitializeParams;
use Phpactor\LanguageServer\Core\Server\Transmitter\MessageTransmitter;
use Phpactor\LanguageServer\Core\Dispatcher\Factory\ClosureDispatcherFactory;
use Phpactor\LanguageServer\LanguageServerBuilder;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher as TYPO3MiddlewareDispatcher;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use Psr\Log\NullLogger;
use Teddytrombone\IdeCompanion\AcmeLsDispatcherFactory;

class LspCommand extends Command
{

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageKey = 'ide_companion';
        DebuggerUtility::var_dump((ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php')), null, 8, true);
        if (file_exists(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'))) {
            DebuggerUtility::var_dump('load');
            require_once(ExtensionManagementUtility::extPath($packageKey, 'Resources/Private/Vendor/autoload.php'));
        }
        $logger = new NullLogger();
        LanguageServerBuilder::create(new AcmeLsDispatcherFactory($logger))
            ->build()
            ->run();
        return 0;
    }
}
