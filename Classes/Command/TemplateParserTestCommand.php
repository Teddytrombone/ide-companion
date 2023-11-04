<?php

declare(strict_types=1);

namespace Teddytrombone\IdeCompanion\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3Fluid\Fluid\Core\Parser\Patterns;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Teddytrombone\IdeCompanion\Utility\ViewHelperUtility;

class TemplateParserTestCommand extends Command
{
    private const NOT_FLUID = 'not-fluid';

    private const SUBPATTERN_VIEWHELPER = '[a-zA-Z0-9\\.]';

    private const PATTERN_TAG_START = '/
        [<{]
            (' . self::SUBPATTERN_VIEWHELPER . '*)
            (?:
                \:
                (' . self::SUBPATTERN_VIEWHELPER . '*)
            )?
        $/xs';


    private const PATTERN_VIEWHELPER_TAG_WITH_ATTRIBUTES_NOT_CLOSED = '/
        <
            (' . self::SUBPATTERN_VIEWHELPER . '+) # Namespace
            :
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
            [a-zA-Z0-9\|\->_:=,.()*+\^\/\%\s]*     # Various characters including math operations and spaces
            (' . self::SUBPATTERN_VIEWHELPER . '+) # Namespace
            :
            (' . self::SUBPATTERN_VIEWHELPER . '+) # ViewHelper
        \(
        (.*?)
        $/xs';

    private const PATTERN_VIEWHELPER_SHORTHANDSYNTAX_COMPLETION_NOT_NEEDS_ATTRIBUTES = '/
        :\s*[\\\\]*
		(?>                          # either... # If we have found an argument, we will not back-track (That does the Atomic Bracket)
			"[^"]*                   # a double-quoted string
			|\'[^\']*                # or a single quoted string
		)?                           #
    $/xs';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = [
            ExtensionManagementUtility::extPath('ide_companion') . 'Resources/Private/TestTemplates/Template.html',
            ExtensionManagementUtility::extPath('ide_companion') . 'Resources/Private/TestTemplates/ShorthandTemplate.html',
        ];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $tags = GeneralUtility::makeInstance(ViewHelperUtility::class)->getPossibleTagsFromSource($content);
            print_r($tags['bcgeneric']['svg.icon']);
            die();
            if (!preg_match_all('/###(\d+)###/msi', $content, $matches, PREG_SET_ORDER)) {
                return 1;
            }
            $positions = [];
            foreach ($matches as $match) {
                $positions[$match[1]] = strpos($content, $match[0]);
                $content = str_replace($match[0], '', $content);
                var_dump($match[1] . ': ' . $this->parseForFluidTag($content, $positions[$match[1]], ['f']));
            }
        }

        return 0;
    }

    protected function parseForFluidTag($content, $position, $allowedNamespaces)
    {
        $before = substr($content, 0, $position);
        $splitted = preg_split(Patterns::$SPLIT_PATTERN_TEMPLATE_DYNAMICTAGS, $before, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $lastSplit = array_pop($splitted);
        $sections = preg_split(Patterns::$SPLIT_PATTERN_SHORTHANDSYNTAX, $lastSplit, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $lastSection = array_pop($sections);
        if (preg_match(self::PATTERN_TAG_START, $lastSplit, $match)) {
            $currentNamespace = $match[1] ?? null;
            $foundNamespaces = [];
            if (!empty($currentNamespace)) {
                $foundNamespaces = array_filter($allowedNamespaces, function ($namespace) use ($currentNamespace) {
                    return $namespace === $currentNamespace || str_starts_with($namespace, $currentNamespace);
                });
                if (empty($foundNamespaces)) {
                    return self::NOT_FLUID;
                }
            }
            return 'tagStart';
        } else if (preg_match(self::PATTERN_VIEWHELPER_TAG_WITH_ATTRIBUTES_NOT_CLOSED, $lastSplit, $match)) {
            if (preg_match(self::PATTERN_VIEWHELPER_COMPLETION_NOT_NEEDS_ATTRIBUTES, $match[0], $innerMatch)) {
                return 'inside-attribute';
            }
            return 'attribute';
        } else if (preg_match(self::PATTERN_VIEWHELPER_SHORHANDSYNTAX_WITH_ATTRIBUTES_NOT_CLOSED, $lastSection, $match)) {
            $lastMatch = $match;
            $innerMatch = [];
            do {
                $currentMatch = preg_match(self::PATTERN_VIEWHELPER_SHORHANDSYNTAX_WITH_ATTRIBUTES_NOT_CLOSED, $lastMatch[3], $innerMatch);
                if ($currentMatch) {
                    $lastMatch = $innerMatch;
                }
            } while ($currentMatch);

            if (preg_match(self::PATTERN_VIEWHELPER_SHORTHANDSYNTAX_COMPLETION_NOT_NEEDS_ATTRIBUTES, $lastMatch[0], $innerMatch)) {
                return 'inside-attribute';
            }
            return 'attribute';
        }
    }
}
