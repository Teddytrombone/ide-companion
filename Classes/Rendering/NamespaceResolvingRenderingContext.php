<?php

namespace Teddytrombone\IdeCompanion\Rendering;

use TYPO3Fluid\Fluid\Core\Cache\FluidCacheInterface;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\ErrorHandler\ErrorHandlerInterface;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInvoker;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3Fluid\Fluid\View\TemplatePaths;

class NamespaceResolvingRenderingContext implements RenderingContextInterface
{
    public function getErrorHandler()
    {
    }

    public function setErrorHandler(ErrorHandlerInterface $errorHandler)
    {
    }

    public function setVariableProvider(VariableProviderInterface $variableProvider)
    {
    }

    public function setViewHelperVariableContainer(ViewHelperVariableContainer $viewHelperVariableContainer)
    {
    }

    public function getVariableProvider()
    {
    }

    public function getViewHelperVariableContainer()
    {
    }

    public function getViewHelperResolver()
    {
    }

    public function setViewHelperResolver(ViewHelperResolver $viewHelperResolver)
    {
    }

    public function getViewHelperInvoker()
    {
    }

    public function setViewHelperInvoker(ViewHelperInvoker $viewHelperInvoker)
    {
    }

    public function setTemplateParser(TemplateParser $templateParser)
    {
    }

    public function getTemplateParser()
    {
    }

    public function setTemplateCompiler(TemplateCompiler $templateCompiler)
    {
    }

    public function getTemplateCompiler()
    {
    }

    public function getTemplatePaths()
    {
    }

    public function setTemplatePaths(TemplatePaths $templatePaths)
    {
    }

    public function setCache(FluidCacheInterface $cache)
    {
    }

    public function getCache()
    {
    }

    public function isCacheEnabled()
    {
    }

    public function setTemplateProcessors(array $templateProcessors)
    {
    }

    public function getTemplateProcessors()
    {
    }

    public function getExpressionNodeTypes()
    {
    }

    public function setExpressionNodeTypes(array $expressionNodeTypes)
    {
    }

    public function buildParserConfiguration()
    {
    }

    public function getControllerName()
    {
    }

    public function setControllerName($controllerName)
    {
    }

    public function getControllerAction()
    {
    }

    public function setControllerAction($action)
    {
    }
}
