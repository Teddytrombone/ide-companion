<?php

namespace Teddytrombone\IdeCompanion\Utility;

use Doctrine\RST\Configuration;
use Doctrine\RST\Kernel;
use Doctrine\RST\Parser;
use League\HTMLToMarkdown\HtmlConverter;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3\CMS\Core\Core\ClassLoadingInformation;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use phpDocumentor\Reflection\DocBlockFactoryInterface;

class ViewHelperUtility
{

    protected $globalNamespaces = [];

    /**
     * @var DocBlockFactory
     */
    protected $docBlockFactory;

    /**
     * @var ContextFactory
     */
    protected $contextFactory;

    /**
     * @var Parser
     */
    protected $rstParser;

    /**
     * @var HtmlConverter
     */
    protected $htmlConverter;


    public function __construct(?DocBlockFactoryInterface $docBlockFactory = null)
    {
        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();

        $configuration = new Configuration();
        $configuration->silentOnError(true);
        $configuration->abortOnError(false);
        $configuration->setIgnoreInvalidReferences(true);
        $kernel = new Kernel($configuration);
        $this->rstParser = new Parser($kernel);
        $this->htmlConverter = new HtmlConverter();
    }

    /**
     * @return array<string,array<int,string>>
     */
    protected function loadNamespacesFromSource(string $content): array
    {
        $renderingContext = GeneralUtility::makeInstance(RenderingContext::class);
        $renderingContext->getTemplateParser()->parse($content);
        return $renderingContext->getViewHelperResolver()->getNamespaces();
    }

    public function getPossibleTagsFromSource(string $content)
    {
        $namespacesToReturn = [];
        $viewHelperClasses = $this->getViewHelperClasses();
        foreach ($this->loadNamespacesFromSource($content) as $prefix => $namespaces) {
            $viewHelperTagNames = [];
            foreach ($namespaces as $namespace) {
                foreach ($viewHelperClasses as $class) {
                    if (str_starts_with($class, $namespace)) {
                        $tagName = $this->getTagNameForClass($class, $namespace);
                        $viewHelperTagNames[$tagName] = $class;
                    }
                }
            }

            // TODO: refactor to cache reflected classes
            foreach ($viewHelperTagNames as $tagName => $class) {
                $reflectionClass = new ReflectionClass($class);
                if (
                    !$reflectionClass->implementsInterface(ViewHelperInterface::class) ||
                    !$reflectionClass->isInstantiable()
                ) {
                    continue;
                }
                $namespacesToReturn[$prefix][$tagName] = [
                    'class' => $class,
                    'tagName' => $tagName,
                    'description' => $this->getDescription($reflectionClass),
                    'arguments' => $this->getArgumentDefinition($reflectionClass),
                    'file' => $reflectionClass->getFileName(),
                    'range' => new Range(new Position($reflectionClass->getStartLine(), 0), new Position($reflectionClass->getEndLine(), 0)),
                ];
            }
        }
        return $namespacesToReturn;
    }

    /**
     * @param ReflectionClass $reflectionClass
     */
    protected function getArgumentDefinition(ReflectionClass $reflectionClass): array
    {
        $viewHelper = $reflectionClass->newInstanceWithoutConstructor();
        return $viewHelper->prepareArguments();
    }

    /**
     * @param ReflectionClass $reflectionClass
     */
    protected function getDescription(ReflectionClass $reflectionClass): string
    {
        $docComment = $reflectionClass->getDocComment();
        if ($docComment) {
            $parsed = $this->docBlockFactory->create($docComment, $this->contextFactory->createFromReflector($reflectionClass));
            $description = $parsed->getDescription();
            if (!empty($description)) {
                $description = $this->rstParser->parse($parsed->getDescription())->render();
                $description = strip_tags($this->htmlConverter->convert($description));
            }

            return trim($parsed->getSummary() . "\n\n" . $description);
        }
        return '';
    }

    protected function getTagNameForClass(string $className, string $namespace): string
    {
        $separator = false !== strpos($className, '\\') ? '\\' : '_';
        $base = substr(substr($className, strlen($namespace) + 1), 0, -10);
        $classNameParts = explode($separator, $base);
        $classNameParts = array_map('lcfirst', $classNameParts);
        $tagName = implode('.', $classNameParts);
        return $tagName;
    }

    /**
     * @return string[] array of all classes ending with 'ViewHelper' so they are possible ViewHelpers ;-)
     */
    public function getViewHelperClasses(): array
    {
        $classes = array_filter(array_keys(ClassLoadingInformation::getClassLoader()->getClassMap()), function ($className) {
            return str_ends_with($className, 'ViewHelper');
        });
        return $classes;
    }
}
