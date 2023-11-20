<?php

namespace Teddytrombone\IdeCompanion\Lsp\Completion;

use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\DocumentLink;
use Phpactor\LanguageServerProtocol\DocumentLinkParams;
use Phpactor\LanguageServerProtocol\Range;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Teddytrombone\IdeCompanion\Lsp\Converter\PositionConverter;

class ExtensionPathCompletionHandler implements Handler, CanRegisterCapabilities
{
    private const EXTENSION_PATH_REGEX = '#EXT:[a-zA-Z0-9\.\-_/]+#';

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
            'textDocument/documentLink' => 'links'
        ];
    }

    public function links(DocumentLinkParams $params)
    {
        return \Amp\call(function () use ($params) {
            $documentLinks = [];
            $textDocument = $this->workspace->get($params->textDocument->uri);
            $content = $textDocument->text;
            $position = 0;
            if (preg_match_all(self::EXTENSION_PATH_REGEX, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $path = GeneralUtility::getFileAbsFileName($match[0]);
                    if (!$path) {
                        continue;
                    }
                    $foundPosition = strpos($content, $match[0], $position);
                    $position = $foundPosition + strlen($match[0]);
                    $documentLinks[] = new DocumentLink(new Range(PositionConverter::intByteOffsetToPosition($foundPosition, $content), PositionConverter::intByteOffsetToPosition($position, $content)), $path);
                }
            }
            return $documentLinks;
        });
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $capabilities->documentLinkProvider = true;
    }
}
