<?php

namespace Teddytrombone\IdeCompanion\Utility;

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

    protected $contextFactory;

    public function __construct(?DocBlockFactoryInterface $docBlockFactory = null)
    {
        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();
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
            return $parsed->getSummary() . "\n\n" . $parsed->getDescription();
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
