<?php

namespace Teddytrombone\IdeCompanion\Lsp\Completion;

use Amp\CancellationToken;
use Amp\Promise;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionItemKind;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\DefinitionOptions;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
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

    /**
     * @var ViewHelperUtility
     */
    private $viewHelperUtility;

    /**
     * @var CompletionParser
     */
    private $completionParser;

    public function __construct(Workspace $workspace, ?ViewHelperUtility $viewHelperUtility = null, ?CompletionParser $completionParser = null)
    {
        $this->workspace = $workspace;
        $this->viewHelperUtility = $viewHelperUtility ?? GeneralUtility::makeInstance(ViewHelperUtility::class);
        $this->completionParser = $completionParser ?? GeneralUtility::makeInstance(CompletionParser::class);
    }


    public function methods(): array
    {
        return [
            'textDocument/completion' => 'complete',
            'textDocument/definition' => 'definition',
        ];
    }

    public function definition(DefinitionParams $params): Promise
    {
        return \Amp\call(function () use ($params) {
            $textDocument = $this->workspace->get($params->textDocument->uri);
            $offset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);

            $namespacedTags = $this->viewHelperUtility->getPossibleTagsFromSource($textDocument->text);
            $result = $this->completionParser->parseForCompleteFluidTag($textDocument->text, $offset->toInt(), array_keys($namespacedTags));

            if (
                in_array($result->getStatus(), [ParsedTagResult::STATUS_TAG, ParsedTagResult::STATUS_ATTRIBUTE])
            ) {
                $file = $namespacedTags[$result->getNamespace()][$result->getTag()]['file'] ?? null;
                $range = $namespacedTags[$result->getNamespace()][$result->getTag()]['range'] ?? null;
                if ($file && $range) {
                    return new Location('file://' . $file, $range);
                }
            }
            return null;
        });
    }

    public function complete(CompletionParams $params, CancellationToken $cancellation): Promise
    {
        return \Amp\call(function () use ($params, $cancellation) {
            $completionItems = [];

            $textDocument = $this->workspace->get($params->textDocument->uri);
            $offset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);

            $namespacedTags = $this->viewHelperUtility->getPossibleTagsFromSource($textDocument->text);
            $result = $this->completionParser->parseForFluidTag($textDocument->text, $offset->toInt(), array_keys($namespacedTags));

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
            return $completionItems ?? [];
        });
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
        $capabilities->completionProvider = new CompletionOptions(['<', '{', '(', ' ']);
        $capabilities->definitionProvider = true;
    }
}
