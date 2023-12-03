<?php

namespace Teddytrombone\IdeCompanion\Lsp\LanguageServer;

use Phpactor\LanguageServer\Adapter\Psr\AggregateEventDispatcher;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\PassThroughArgumentResolver;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\LanguageSeverProtocolParamsResolver;
use Phpactor\LanguageServer\Core\Dispatcher\ArgumentResolver\ChainArgumentResolver;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServer\Listener\WorkspaceListener;
use Phpactor\LanguageServer\Middleware\CancellationMiddleware;
use Phpactor\LanguageServer\Middleware\ErrorHandlingMiddleware;
use Phpactor\LanguageServer\Middleware\InitializeMiddleware;
use Phpactor\LanguageServerProtocol\InitializeParams;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher;
use Phpactor\LanguageServer\Core\Handler\HandlerMethodRunner;
use Phpactor\LanguageServer\Core\Dispatcher\DispatcherFactory;
use Phpactor\LanguageServer\Handler\Workspace\CommandHandler;
use Phpactor\LanguageServer\Middleware\ResponseHandlingMiddleware;
use Phpactor\LanguageServer\Core\Command\CommandDispatcher;
use Phpactor\LanguageServer\Core\Diagnostics\AggregateDiagnosticsProvider;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsEngine;
use Phpactor\LanguageServer\Handler\System\ServiceHandler;
use Phpactor\LanguageServer\Core\Handler\Handlers;
use Phpactor\LanguageServer\Handler\TextDocument\TextDocumentHandler;
use Phpactor\LanguageServer\Core\Dispatcher\Dispatcher\MiddlewareDispatcher;
use Phpactor\LanguageServer\Listener\ServiceListener;
use Phpactor\LanguageServer\Core\Server\RpcClient\JsonRpcClient;
use Phpactor\LanguageServer\Core\Service\ServiceManager;
use Phpactor\LanguageServer\Core\Service\ServiceProviders;
use Phpactor\LanguageServer\Core\Server\ClientApi;
use Phpactor\LanguageServer\Core\Server\ResponseWatcher\DeferredResponseWatcher;
use Phpactor\LanguageServer\Core\Server\Transmitter\MessageTransmitter;
use Phpactor\LanguageServer\Middleware\HandlerMiddleware;
use Phpactor\LanguageServer\Middleware\ShutdownMiddleware;
use Phpactor\LanguageServer\Service\DiagnosticsService;
use Psr\Log\LoggerInterface;
use Teddytrombone\IdeCompanion\Lsp\Completion\ExtensionPathCompletionHandler;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Teddytrombone\IdeCompanion\Lsp\Completion\FluidCompletionHandler;
use Teddytrombone\IdeCompanion\Lsp\Diagnostics\FluidDiagnosticsProvider;
use Teddytrombone\IdeCompanion\Lsp\Diagnostics\TestDiagnosticsProvider;
use Teddytrombone\IdeCompanion\Utility\LoggingUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluidLsDispatcherFactory implements DispatcherFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        GeneralUtility::makeInstance(LoggingUtility::class)->setLogger($logger);
    }

    public function create(MessageTransmitter $transmitter, InitializeParams $initializeParams): Dispatcher
    {
        $responseWatcher = new DeferredResponseWatcher();
        $clientApi = new ClientApi(new JsonRpcClient($transmitter, $responseWatcher));

        $workspace = new Workspace();

        $diagnosticsService = new DiagnosticsService(
            new DiagnosticsEngine($clientApi, new AggregateDiagnosticsProvider(
                $this->logger,
                new FluidDiagnosticsProvider()
            )),
            true,
            true,
            $workspace
        );

        $serviceProviders = new ServiceProviders(
            $diagnosticsService
        );

        $serviceManager = new ServiceManager($serviceProviders, $this->logger);

        $eventDispatcher = new AggregateEventDispatcher(
            $diagnosticsService,
            new ServiceListener($serviceManager),
            new WorkspaceListener($workspace)
        );

        $handlers = new Handlers(
            new ExtensionPathCompletionHandler($workspace),
            new FluidCompletionHandler($workspace),
            new TextDocumentHandler($eventDispatcher),
            new ServiceHandler($serviceManager, $clientApi),
            new CommandHandler(new CommandDispatcher([])),
        );

        $runner = new HandlerMethodRunner(
            $handlers,
            new ChainArgumentResolver(
                new LanguageSeverProtocolParamsResolver(),
                new PassThroughArgumentResolver()
            ),
        );

        return new MiddlewareDispatcher(
            new ErrorHandlingMiddleware($this->logger),
            new InitializeMiddleware($handlers, $eventDispatcher, [
                'name' => 'fluid-ide-companion',
                'version' => ExtensionManagementUtility::getExtensionVersion('ide_companion'),
            ]),
            new ShutdownMiddleware($eventDispatcher),
            new ResponseHandlingMiddleware($responseWatcher),
            new CancellationMiddleware($runner),
            new HandlerMiddleware($runner)
        );
    }
}
