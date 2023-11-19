<?php

namespace Teddytrombone\IdeCompanion\Tests\Unit\Fluid;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Teddytrombone\IdeCompanion\Parser\CompletionParser;
use Teddytrombone\IdeCompanion\Parser\ParsedTagResult;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class TagCompletionParserTest extends UnitTestCase
{
    protected $simpleTag = '<f:if condition="foobar" />';
    //                      012345678911111111112222222
    //                      ----------01234567890123456

    protected $simpleShorthandTag = "{f:if(condition:'foobar'}";
    //                               0123456789111111111222222
    //                               ----------012345678901234


    protected $simpleTagTests = [
        [
            'position' => 0,
            'status' => ParsedTagResult::STATUS_NO_FLUID_TAG,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 1,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 2,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => 'f',
            'tag' => null,
        ],
        [
            'position' => 3,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => null,
        ],
        [
            'position' => 4,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => 'i',
        ],
        [
            'position' => 5,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => 'if',
        ],
        [
            'position' => 6,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'if',
        ],
        [
            'position' => 9,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'if',
        ],
        [
            'position' => 15,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'if',
        ],
        [
            'position' => 16,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 17,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
    ];

    protected $endTag = '</bcgeneric:svg.icon>';
    //                   012345678911111111122
    //                   ----------01234567890

    protected $endTagTests = [
        [
            'position' => 0,
            'status' => ParsedTagResult::STATUS_NO_FLUID_TAG,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 1,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 2,
            'status' => ParsedTagResult::STATUS_END_TAG,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 3,
            'status' => ParsedTagResult::STATUS_END_TAG,
            'namespace' => 'b',
            'tag' => null,
        ],
        [
            'position' => 8,
            'status' => ParsedTagResult::STATUS_END_TAG,
            'namespace' => 'bcgene',
            'tag' => null,
        ],
        [
            'position' => 11,
            'status' => ParsedTagResult::STATUS_END_TAG,
            'namespace' => 'bcgeneric',
            'tag' => null,
        ],
        [
            'position' => 20,
            'status' => ParsedTagResult::STATUS_END_TAG,
            'namespace' => 'bcgeneric',
            'tag' => 'svg.icon',
        ],
        [
            'position' => 21,
            'status' => ParsedTagResult::STATUS_END_TAG,
            'namespace' => 'bcgeneric',
            'tag' => 'svg.icon',
        ],
    ];


    protected $tagWithLongerNamespaceAndTag = '<bcgeneric:svg.icon icon= "foobar" />';
    //                                         0123456789111111111122222222223333333
    //                                         ----------012345678901234567890123456

    protected $shorthandTagWithLongerNamespaceAndTag = "{bcgeneric:svg.icon(icon: 'foobar'}";
    //                                                  012345678911111111112222222222333333
    //                                                  ----------01234567890123456789012345

    protected $longerTagTests = [
        [
            'position' => 0,
            'status' => ParsedTagResult::STATUS_NO_FLUID_TAG,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 1,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 8,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => 'bcgener',
            'tag' => null,
        ],
        [
            'position' => 11,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'bcgeneric',
            'tag' => null,
        ],
        [
            'position' => 12,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'bcgeneric',
            'tag' => 's',
        ],
        [
            'position' => 15,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'bcgeneric',
            'tag' => 'svg.',
        ],
        [
            'position' => 20,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'bcgeneric',
            'tag' => 'svg.icon',
        ],
        [
            'position' => 23,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'bcgeneric',
            'tag' => 'svg.icon',
        ],
        [
            'position' => 24,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'bcgeneric',
            'tag' => 'svg.icon',
        ],
        [
            'position' => 26,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 27,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
    ];

    protected $complexShorthandTag = "{f:variable(name: 'test', value: '{date -> f:format.date(format: \'{f:if(condition: isFalse, then: \\'{f:translate(key: \\\\'test\\\\', default: \\\\'d.m.Y\\\\')}\\', else: \\'{f:translate(key: \\\\'test\\\\', default: \\\\'m/d/Y\\\\')}\\')}\')}')}";
    //                                0123456789111111111122222222223333333333444444444455555555556666666666777777777788888888889999999999
    //                                ----------012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789

    protected $complexTagTests = [
        [
            'position' => 22,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 23,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 24,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'variable',
        ],
        [
            'position' => 25,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'variable',
        ],
        [
            'position' => 26,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'variable',
        ],
        [
            'position' => 31,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'variable',
        ],
        [
            'position' => 32,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 34,
            'status' => ParsedTagResult::STATUS_INSIDE_ATTRIBUTE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 35,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 36,
            'status' => ParsedTagResult::STATUS_NO_FLUID_TAG,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 39,
            'status' => ParsedTagResult::STATUS_NO_FLUID_TAG,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 43,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 45,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => null,
        ],
        [
            'position' => 51,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => 'format',
        ],
        [
            'position' => 56,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => 'format.date',
        ],
        [
            'position' => 57,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'format.date',
        ],
        [
            'position' => 63,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'format.date',
        ],
        [
            'position' => 68,
            'status' => ParsedTagResult::STATUS_NAMESPACE,
            'namespace' => null,
            'tag' => null,
        ],
        [
            'position' => 70,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => null,
        ],
        [
            'position' => 72,
            'status' => ParsedTagResult::STATUS_TAG,
            'namespace' => 'f',
            'tag' => 'if',
        ],
        [
            'position' => 73,
            'status' => ParsedTagResult::STATUS_ATTRIBUTE,
            'namespace' => 'f',
            'tag' => 'if',
        ],
    ];

    /**
     * @dataProvider simpleTagProvider
     * @dataProvider longerTagProvider
     * @dataProvider complexTagProvider
     * @dataProvider endTagProvider
     * @test
     * @return void
     */
    public function testViewHelperTags(string $string, int $position, int $status, ?string $namespace, ?string $tag, bool $isShorthand): void
    {
        $parser = GeneralUtility::makeInstance(CompletionParser::class);
        $allowedNamespaces = ['f', 'bcgeneric'];
        $result = $parser->parseForFluidTag($string, $position, $allowedNamespaces);
        $this->assertEquals($status, $result->getStatus());
        $this->assertEquals($namespace, $result->getNamespace());
        $this->assertEquals($tag, $result->getTag());
        $this->assertEquals(
            $isShorthand && in_array($result->getStatus(), [ParsedTagResult::STATUS_NO_FLUID_TAG, ParsedTagResult::STATUS_INSIDE_ATTRIBUTE]) === false,
            $result->isShorthand()
        );
    }

    /**
     * @test
     */

    public function testFullViewHelperTagsFromPosition()
    {
        $string = 'foobar<f:translate key="test" />';
        $parser = GeneralUtility::makeInstance(CompletionParser::class);
        $allowedNamespaces = ['f', 'bcgeneric'];
        $result = $parser->parseForCompleteFluidTag($string, 10, $allowedNamespaces);
        $this->assertEquals(ParsedTagResult::STATUS_TAG, $result->getStatus());
        $this->assertEquals('f', $result->getNamespace());
        $this->assertEquals('translate', $result->getTag());
    }

    public function simpleTagProvider(): array
    {
        return array_merge(
            $this->getTestsWithTag($this->simpleTag, false, $this->simpleTagTests),
            $this->getTestsWithTag($this->simpleShorthandTag, true, $this->simpleTagTests)
        );
    }

    public function longerTagProvider(): array
    {
        return array_merge(
            $this->getTestsWithTag($this->tagWithLongerNamespaceAndTag, false, $this->longerTagTests),
            $this->getTestsWithTag($this->shorthandTagWithLongerNamespaceAndTag, true, $this->longerTagTests)
        );
    }

    public function complexTagProvider(): array
    {
        return $this->getTestsWithTag($this->complexShorthandTag, true, $this->complexTagTests);
    }

    public function endTagProvider(): array
    {
        return $this->getTestsWithTag($this->endTag, false, $this->endTagTests);
    }

    protected function getTestsWithTag(string $tag, bool $isShorthand, array $tests): array
    {
        return array_map(function ($element) use ($tag, $isShorthand) {
            return [
                $tag,
                $element['position'],
                $element['status'],
                $element['namespace'] ?? null,
                $element['tag'] ?? null,
                $isShorthand,
            ];
        }, $tests);
    }
}
