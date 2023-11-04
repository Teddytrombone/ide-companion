<?php

namespace Teddytrombone\IdeCompanion\Lsp\Completion;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;
use Generator;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\TextDocumentSyncKind;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionItemKind;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\CompletionTriggerKind;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Teddytrombone\IdeCompanion\Lsp\Converter\PositionConverter;
use Teddytrombone\IdeCompanion\Parser\CompletionParser;
use Teddytrombone\IdeCompanion\Parser\ParsedTagResult;
use Teddytrombone\IdeCompanion\Utility\ViewHelperUtility;

class FluidCompletionHandler implements Handler, CanRegisterCapabilities
{
    /**
     * @var Workspace
     */
    private $workspace;

    public function __construct(Workspace $workspace)
    {
        $this->workspace = $workspace;
    }


    public function methods(): array
    {
        return [
            'textDocument/completion' => 'complete',
        ];
    }

    public function complete(CompletionParams $completionParams, CancellationToken $cancellation): Promise
    {
        return \Amp\call(function () use ($completionParams, $cancellation) {
            $uriToTextDocument = $completionParams->textDocument->uri;
            $doc = $this->workspace->get($uriToTextDocument);
            $viewHelperUtility = GeneralUtility::makeInstance(ViewHelperUtility::class);
            $namespacedTags = $viewHelperUtility->getPossibleTagsFromSource($doc->text);
            $completionItems = [];
            $byteOffset = PositionConverter::positionToByteOffset($completionParams->position, $doc->text);

            $parser = GeneralUtility::makeInstance(CompletionParser::class);
            $result = $parser->parseForFluidTag($doc->text, $byteOffset->toInt(), array_keys($namespacedTags));

            switch ($result->getStatus()) {
                case ParsedTagResult::STATUS_NAMESPACE:
                case ParsedTagResult::STATUS_TAG:
                    foreach ($this->filterTags($namespacedTags, $result, $cancellation) as $item) {
                        $completionItems[] = $item;
                        yield \Amp\delay(1);
                        if ($cancellation->isRequested()) {
                            break;
                        }
                    }
                    break;
                case ParsedTagResult::STATUS_ATTRIBUTE:
                    $tag = $namespacedTags[$result->getNamespace()][$result->getTag()] ?? null;
                    if (empty($tag['arguments'])) {
                        $completionItems[] = new CompletionItem('completion: ' . $result->getNamespace() . ':' . $result->getTag());
                        break;
                    }
                    /** @var \TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition $argument */
                    foreach ($tag['arguments'] as $argument) {
                        $completionItems[] = new CompletionItem(
                            $argument->getName(),
                            CompletionItemKind::FIELD,
                            null,
                            $argument->getType(),
                            $argument->getDescription()
                        );
                        yield \Amp\delay(1);
                        if ($cancellation->isRequested()) {
                            break;
                        }
                    }
                    break;
            }
            /*
            foreach ($namespacedTags as $namespace => $tags) {
                if ($completionParams->context->triggerKind === CompletionTriggerKind::TRIGGER_CHARACTER) {
                    foreach ($tags as $ta => $tagConfig) {
                        $completionItems[] = new CompletionItem(
                            $namespace . ':' . $ta,
                            CompletionItemKind::FUNCTION,
                            null,
                            $tagConfig['description'],
                        );
                    }
                }
                yield \Amp\delay(1);
                try {
                    $cancellation->throwIfRequested();
                } catch (\Amp\CancelledException $cancelled) {
                    break;
                }
            }
*/
            return $completionItems ?? [];
        });
        //        file_put_contents('file.txt', print_r([$doc, $completionParams], true));
        //      return new Success("Test");
    }

    protected function filterTags($namespacedTags, ParsedTagResult $result)
    {
        $namespaceEquals = $result->getStatus() === ParsedTagResult::STATUS_TAG;
        $anyTag = empty($result->getTag());
        foreach ($namespacedTags as $namespace => $tags) {
            if (
                ($namespaceEquals && $namespace !== $result->getNamespace()) ||
                (!$namespaceEquals && !str_starts_with($namespace, $result->getNamespace() ?? ''))
            ) {
                continue;
            }
            foreach ($tags as $tag => $tagConfig) {
                if (!$anyTag && !str_starts_with($tag, $result->getTag() ?? '')) {
                    continue;
                }
                yield new CompletionItem(
                    $namespace . ':' . $tag,
                    CompletionItemKind::FUNCTION,
                    null,
                    null,
                    $tagConfig['description'],
                );
            }
        }
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->completionProvider = new CompletionOptions(['<', ':', '{']);
    }
}
