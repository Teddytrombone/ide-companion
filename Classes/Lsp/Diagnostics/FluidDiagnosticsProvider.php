<?php

namespace Teddytrombone\IdeCompanion\Lsp\Diagnostics;

use Amp\CancellationToken;
use Amp\Promise;
use Phpactor\LanguageServer\Core\Diagnostics\DiagnosticsProvider;
use Phpactor\LanguageServerProtocol\Diagnostic;
use Phpactor\LanguageServerProtocol\DiagnosticSeverity;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Teddytrombone\IdeCompanion\Lsp\Converter\PositionConverter;
use Teddytrombone\IdeCompanion\Utility\LoggingUtility;
use Teddytrombone\IdeCompanion\Utility\ViewHelperUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FluidDiagnosticsProvider implements DiagnosticsProvider
{
    /**
     * {@inheritDoc}
     */
    public function provideDiagnostics(TextDocumentItem $textDocument, CancellationToken $cancel): Promise
    {
        return \Amp\call(function () use ($textDocument) {
            $errors = GeneralUtility::makeInstance(ViewHelperUtility::class)->getCodeErrors($textDocument->text);
            $ret = [];
            foreach ($errors ?? [] as $error) {
                $range = $this->convertFluidParsingPointerToRange($error['position'], $textDocument->text);
                if ($range === null) {
                    continue;
                }
                $ret[] = new Diagnostic(
                    $range,
                    $error['message'],
                    DiagnosticSeverity::ERROR
                );
            }
            GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r($ret, true));
            return $ret;
        });
    }

    public function name(): string
    {
        return 'teddy-test';
    }

    private function convertFluidParsingPointerToRange(array $pointer, string $text): ?Range
    {
        $line = $pointer[0] ?? null;
        $column = $pointer[1] ?? null;
        $problematicContent = trim($pointer[2] ?? '');
        $length = mb_strlen($problematicContent);
        if ($line === null || $column === null || $length === 0) {
            return null;
        }
        // Bercause the position pointer is quite fuzzy, we need to find the problematic code in the text
        $pos = mb_strpos($text, $problematicContent);

        $startPosition = PositionConverter::intByteOffsetToPosition($pos, $text);
        $endByteOffset = PositionConverter::positionToByteOffset($startPosition, $text)->add($length);
        $endPosition = PositionConverter::byteOffsetToPosition($endByteOffset, $text);
        /*
        GeneralUtility::makeInstance(LoggingUtility::class)->getLogger()->debug(print_r([
            $pointer,
            $startPosition,
            (new LineCol($line, $column))->toByteOffset($text),
            PositionConverter::positionToByteOffset($startPosition, $text),
            $endPosition,
            $endByteOffset,
        ], true));*/
        return new Range($startPosition, $endPosition);
    }
}
