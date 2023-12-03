<?php

namespace Teddytrombone\IdeCompanion\Lsp\Completion;

use Amp\CancellationToken;
use Amp\Promise;
use Generator;
use Phpactor\LanguageServerProtocol\InsertTextFormat;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionItemKind;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverParams;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\MarkupContent;
use Phpactor\LanguageServerProtocol\MarkupKind;
use Phpactor\LanguageServerProtocol\Range;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Teddytrombone\IdeCompanion\Lsp\Converter\PositionConverter;
use Teddytrombone\IdeCompanion\Parser\CompletionParser;
use Teddytrombone\IdeCompanion\Parser\ParsedTagResult;
use Teddytrombone\IdeCompanion\Utility\LoggingUtility;
use Teddytrombone\IdeCompanion\Utility\ViewHelperUtility;

class FluidCompletionHandler implements Handler, CanRegisterCapabilities
{
    private const INSERT_TAG = '%1$s:%2$s %3$s';
    private const INSERT_SHORTHAND = '%s:%s(%s)';

    private const INSERT_TAG_ATTRIBUTE = '%s="%s"';
    private const INSERT_SHORTHAND_ATTRIBUTE = '%s: %s';

    private const INSERT_TAG_SEPARATOR = ' ';
    private const INSERT_SHORTHAND_SEPARATOR = ', ';

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


    public function __construct(
        Workspace $workspace,
        ?ViewHelperUtility $viewHelperUtility = null,
        ?CompletionParser $completionParser = null
    ) {
        $this->workspace = $workspace;
        $this->viewHelperUtility = $viewHelperUtility ?? GeneralUtility::makeInstance(ViewHelperUtility::class);
        $this->completionParser = $completionParser ?? GeneralUtility::makeInstance(CompletionParser::class);
    }


    public function methods(): array
    {
        return [
            'textDocument/completion' => 'complete',
            'textDocument/definition' => 'definition',
            'textDocument/hover' => 'hover',
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
                in_array($result->getStatus(), [ParsedTagResult::STATUS_TAG, ParsedTagResult::STATUS_ATTRIBUTE, ParsedTagResult::STATUS_END_TAG])
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

    public function hover(HoverParams $params): Promise
    {
        GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r([$params], true));
        return \Amp\call(function () use ($params) {
            $textDocument = $this->workspace->get($params->textDocument->uri);
            $offset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);

            GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r([$offset, $offset->toInt()], true));
            $namespacedTags = $this->viewHelperUtility->getPossibleTagsFromSource($textDocument->text);
            $result = $this->completionParser->parseForCompleteFluidTag($textDocument->text, $offset->toInt(), array_keys($namespacedTags));

            if (
                in_array($result->getStatus(), [ParsedTagResult::STATUS_TAG, ParsedTagResult::STATUS_ATTRIBUTE, ParsedTagResult::STATUS_END_TAG])
            ) {
                $tag = $namespacedTags[$result->getNamespace()][$result->getTag()] ?? null;
                if (!$tag) {
                    return null;
                }
                GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r([$result], true));
                $hoverResult = new Hover(new MarkupContent(MarkupKind::MARKDOWN, $tag['description'] ?? ''));

                if ($result->getStartPosition() !== null) {
                    GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(
                        mb_substr($textDocument->text, $result->getStartPosition())
                    );
                    $tagInCode = $result->getNamespace() . ':' . $result->getTag();
                    $hoverResult->range = new Range(
                        PositionConverter::intByteOffsetToPosition($result->getStartPosition(), $textDocument->text),
                        PositionConverter::intByteOffsetToPosition($result->getStartPosition() + mb_strlen($tagInCode), $textDocument->text)
                    );
                }
                GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r([$hoverResult], true));
                return $hoverResult;
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

            GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r([
                $params,
                $textDocument->text,
                $result
            ], true));

            switch ($result->getStatus()) {
                case ParsedTagResult::STATUS_NAMESPACE:
                case ParsedTagResult::STATUS_TAG:
                    foreach ($this->filterTags($namespacedTags, $result) as $item) {
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
            return $completionItems;
        });
    }

    /**
     * @param array<string,array<int,string>> $namespacedTags
     * @param ParsedTagResult $result
     * @return Generator
     */
    protected function filterTags(array $namespacedTags, ParsedTagResult $result): Generator
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
                $arguments = [];
                $first = true;
                /** @var \TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition $argument */
                foreach ($tagConfig['arguments'] ?? [] as $argument) {
                    if ($argument->isRequired()) {

                        $arguments[] = sprintf(
                            $result->isShorthand() ? self::INSERT_SHORTHAND_ATTRIBUTE : self::INSERT_TAG_ATTRIBUTE,
                            $argument->getName(),
                            $first ? '$0' : ''
                        );
                        $first = false;
                    }
                }
                $insertText = sprintf(
                    $result->isShorthand() ? self::INSERT_SHORTHAND : self::INSERT_TAG,
                    $namespace,
                    $tag,
                    count($arguments) > 0 ? implode($result->isShorthand() ? self::INSERT_SHORTHAND_SEPARATOR : self::INSERT_TAG_SEPARATOR, $arguments) : '$0'
                );
                yield new CompletionItem(
                    $namespace . ':' . $tag,
                    CompletionItemKind::FUNCTION,
                    null,
                    null,
                    new MarkupContent(MarkupKind::MARKDOWN, $tagConfig['description']),
                    null,
                    null,
                    null,
                    null,
                    $insertText,
                    InsertTextFormat::SNIPPET
                );
            }
        }
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->completionProvider = new CompletionOptions(['<', '{', '(', ' ']);
        $capabilities->definitionProvider = true;
        $capabilities->hoverProvider = true;
    }
}
