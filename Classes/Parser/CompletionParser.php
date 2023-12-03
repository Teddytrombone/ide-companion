<?php

declare(strict_types=1);

namespace Teddytrombone\IdeCompanion\Parser;

use TYPO3Fluid\Fluid\Core\Parser\Patterns;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * Parses for Fluid tags with regex patterns taken from TYPO3Fluid and adapted so it works with partial fluid tags
 * back from where the cureser is at the moment.
 * As of regexes and TYPO3Fluid not supporting "forgivving" parsing, this is kind of a mess.
 *
 */
class CompletionParser
{
    private const SUBPATTERN_VIEWHELPER = '[a-zA-Z0-9\\.]';

    private const PATTERN_TAG_START = '/
        [<{]
            (' . self::SUBPATTERN_VIEWHELPER . '*)
            (?:
                (:)
                (' . self::SUBPATTERN_VIEWHELPER . '*)
            )?
        $/xs';

    private const PATTERN_TAG_CLOSING = '/
        <\/
            (' . self::SUBPATTERN_VIEWHELPER . '*)
            (?:
                (:)
                (' . self::SUBPATTERN_VIEWHELPER . '*)
            )?
        >?
        $/xs';


    private const PATTERN_VIEWHELPER_TAG_WITH_ATTRIBUTES_NOT_CLOSED = '/
        <
            (' . self::SUBPATTERN_VIEWHELPER . '+) # Namespace
            (:)
            (' . self::SUBPATTERN_VIEWHELPER . '+) # ViewHelper
            \s+
            ([^>]*)
        $/xs';

    private const PATTERN_VIEWHELPER_COMPLETION_NOT_NEEDS_ATTRIBUTES = '/
        \s*
		=                            # =
		\s*
		(?>                          # either... # If we have found an argument, we will not back-track (That does the Atomic Bracket)
			"[^"]*                   # a double-quoted string
			|\'[^\']*                # or a single quoted string
		)?                           #
    $/xs';

    private const PATTERN_VIEWHELPER_SHORHANDSYNTAX_WITH_ATTRIBUTES_NOT_CLOSED = '/
        \{
            (?:[a-zA-Z0-9\|\->_:=,.()*+\^\/\%\s]+[\|\->:=,.()*+\^\/\%\s]+)*     # Various characters including math operations and spaces
            (' . self::SUBPATTERN_VIEWHELPER . '+) # Namespace
            (:)
            (' . self::SUBPATTERN_VIEWHELPER . '+) # ViewHelper
        \(
        (.*)
        $/xs';

    private const PATTERN_VIEWHELPER_SHORHANDSYNTAX_NOT_OPENED = '/
        \{
            [a-zA-Z0-9\|\->_:=,.()*+\^\/\%\s]*     # Various characters including math operations and spaces
            (' . self::SUBPATTERN_VIEWHELPER . '+) # Namespace
            (:)
            (' . self::SUBPATTERN_VIEWHELPER . '+) # ViewHelper
        $/xs';

    private const PATTERN_VIEWHELPER_SHORHANDSYNTAX_CHAINED_NOT_OPENED = '/
        \{
            [a-zA-Z0-9\|\->_:=,.()*+\^\/\%\s]*      # Various characters including math operations and spaces
            (?:\s*->\s*)
            (' . self::SUBPATTERN_VIEWHELPER . '+)? # Namespace
            (:)?
            (' . self::SUBPATTERN_VIEWHELPER . '+)? # ViewHelper
        $/xs';

    private const PATTERN_VIEWHELPER_SHORTHANDSYNTAX_COMPLETION_NOT_NEEDS_ATTRIBUTES = '/
        :\s*[\\\\]*
		(?>                          # either... # If we have found an argument, we will not back-track (That does the Atomic Bracket)
			"[^"]*                   # a double-quoted string
			|\'[^\']*                # or a single quoted string
		)?                           #
    $/xs';

    public function parseForCompleteFluidTag($content, $position, $allowedNamespaces): ParsedTagResult
    {
        $after = substr($content, $position);
        $offset = 0;
        if (preg_match('/^(?:' . self::SUBPATTERN_VIEWHELPER . '|:)+/s', $after, $match)) {
            $offset = strlen($match[0]);
        }
        return $this->parseForFluidTag($content, $position + $offset, $allowedNamespaces);
    }


    public function parseForFluidTag($content, $position, $allowedNamespaces): ParsedTagResult
    {
        $ret = GeneralUtility::makeInstance(ParsedTagResult::class);
        $before = substr($content, 0, $position);
        $splitted = preg_split(Patterns::$SPLIT_PATTERN_TEMPLATE_DYNAMICTAGS, $before, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $lastSplit = array_pop($splitted);
        if ($lastSplit === null) {
            $lastSplit = $before;
        }
        $sections = preg_split(Patterns::$SPLIT_PATTERN_SHORTHANDSYNTAX, $lastSplit, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $lastSection = array_pop($sections);
        if ($lastSection === null) {
            $lastSection = $lastSplit;
        }

        if (preg_match(self::PATTERN_TAG_START, $lastSplit, $match, PREG_OFFSET_CAPTURE)) {
            $currentNamespace = $match[1][0] ?? null;
            $foundNamespaces = [];
            if (!empty($currentNamespace)) {
                $foundNamespaces = array_filter($allowedNamespaces, function ($namespace) use ($currentNamespace) {
                    return $namespace === $currentNamespace || str_starts_with($namespace, $currentNamespace);
                });
                if (empty($foundNamespaces)) {
                    return $ret;
                }
            }
            return $this->genereateParsedTagResultFromPregMatchWithDefaultGroups($match, $lastSplit, $position);
        } elseif (($result = $this->matchTagWithAttributesNotClosed($lastSplit, $position)) !== null) {
            return $result;
        } elseif (($result = $this->matchShorthandSyntaxWithAttributesNotClosed($lastSection, $position)) !== null) {
            return $result;
        } elseif (($result = $this->matchClosingTag($lastSection, $position)) !== null) {
            return $result;
        } elseif (($result = $this->matchShorthandSyntaxChainedNotOpened($lastSection, $position)) !== null) {
            return $result;
        } elseif (($result = $this->matchShorthandSyntaxNotOpened($lastSection, $position)) !== null) {
            return $result;
        }
        return $ret;
    }

    protected function matchTagWithAttributesNotClosed(string $targetString, int $position): ?ParsedTagResult
    {
        $match = [];
        if (!preg_match(self::PATTERN_VIEWHELPER_TAG_WITH_ATTRIBUTES_NOT_CLOSED, $targetString, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $innerMatch = [];
        if (preg_match(self::PATTERN_VIEWHELPER_COMPLETION_NOT_NEEDS_ATTRIBUTES, $match[0][0], $innerMatch)) {
            return GeneralUtility::makeInstance(ParsedTagResult::class)
                ->setStatus(ParsedTagResult::STATUS_INSIDE_ATTRIBUTE);
        }

        return $this->genereateParsedTagResultFromPregMatchWithDefaultGroups($match, $targetString, $position, ParsedTagResult::STATUS_ATTRIBUTE);
    }

    protected function matchShorthandSyntaxWithAttributesNotClosed(string $targetString, int $position): ?ParsedTagResult
    {
        $match = [];
        if (!preg_match(self::PATTERN_VIEWHELPER_SHORHANDSYNTAX_WITH_ATTRIBUTES_NOT_CLOSED, $targetString, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $lastMatch = $match;
        $lastTargetString = $targetString;
        $innerMatch = [];
        do {
            $currentMatch = preg_match(self::PATTERN_VIEWHELPER_SHORHANDSYNTAX_WITH_ATTRIBUTES_NOT_CLOSED, $lastMatch[4][0], $innerMatch, PREG_OFFSET_CAPTURE);
            if ($currentMatch) {
                $lastTargetString = $lastMatch[4][0];
                $lastMatch = $innerMatch;
            }
        } while ($currentMatch);

        if (($result = $this->matchShorthandSyntaxNotOpened($lastMatch[0][0], $position)) !== null) {
            return $result;
        } elseif (($result = $this->matchShorthandSyntaxChainedNotOpened($lastMatch[0][0], $position)) !== null) {
            return $result;
        } elseif (preg_match(self::PATTERN_VIEWHELPER_SHORTHANDSYNTAX_COMPLETION_NOT_NEEDS_ATTRIBUTES, $lastMatch[0][0], $innerMatch)) {
            return GeneralUtility::makeInstance(ParsedTagResult::class)
                ->setStatus(ParsedTagResult::STATUS_INSIDE_ATTRIBUTE);
        }

        $result = $this->genereateParsedTagResultFromPregMatchWithDefaultGroups($lastMatch, $lastTargetString, $position, ParsedTagResult::STATUS_ATTRIBUTE);
        return $result->setIsShorthandFromString($match[0][0]);
    }

    protected function matchClosingTag(string $targetString, int $position): ?ParsedTagResult
    {
        return $this->matchRegexWithOffset(self::PATTERN_TAG_CLOSING, $targetString, $position, ParsedTagResult::STATUS_END_TAG);
    }

    protected function matchShorthandSyntaxChainedNotOpened(string $targetString, int $position): ?ParsedTagResult
    {
        return $this->matchRegexWithOffset(self::PATTERN_VIEWHELPER_SHORHANDSYNTAX_CHAINED_NOT_OPENED, $targetString, $position);
    }

    protected function matchShorthandSyntaxNotOpened(string $targetString, int $position): ?ParsedTagResult
    {
        return $this->matchRegexWithOffset(self::PATTERN_VIEWHELPER_SHORHANDSYNTAX_NOT_OPENED, $targetString, $position);
    }

    protected function matchRegexWithOffset(string $pattern, string $targetString, int $position, ?int $forceStatusOnMatch = null): ?ParsedTagResult
    {
        $match = [];
        if (!preg_match($pattern, $targetString, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return $this->genereateParsedTagResultFromPregMatchWithDefaultGroups($match, $targetString, $position, $forceStatusOnMatch);
    }

    protected function genereateParsedTagResultFromPregMatchWithDefaultGroups(array $match, string $targetString, int $position, ?int $status = null): ParsedTagResult
    {
        if ($status === null || $status === ParsedTagResult::STATUS_NO_FLUID_TAG) {
            $status = ($match[2][0] ?? null) === null ? ParsedTagResult::STATUS_NAMESPACE : ParsedTagResult::STATUS_TAG;
        }

        $ret = GeneralUtility::makeInstance(ParsedTagResult::class);
        $ret
            ->setIsShorthandFromString($match[0][0])
            ->setStatus($status)
            ->setNamespace($match[1][0] ?? null)
            ->setTag($match[3][0] ?? null);

        if (($match[1][1] ?? null) !== null) {
            $ret->setStartPosition($position - strlen($targetString) + $match[1][1]);
        }
        return $ret;
    }
}
