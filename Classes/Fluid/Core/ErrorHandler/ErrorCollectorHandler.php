<?php

declare(strict_types=1);

namespace Teddytrombone\IdeCompanion\Fluid\Core\ErrorHandler;

use TYPO3Fluid\Fluid\Core\ErrorHandler\ErrorHandlerInterface;
use TYPO3Fluid\Fluid\Core\ErrorHandler\TolerantErrorHandler;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class ErrorCollectorHandler implements ErrorHandlerInterface
{
    protected $errors = [];

    /**
     * @var RenderingContextInterface
     */
    protected $renderingContext;

    public function __construct(RenderingContextInterface $renderingContext)
    {
        $this->renderingContext = $renderingContext;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    protected function addError(\Throwable $error)
    {
        $this->errors[] = [
            'message' => $error->getMessage(),
            'position' => $this->renderingContext->getTemplateParser()->getCurrentParsingPointers(),
        ];
    }

    /**
     * @param \TYPO3Fluid\Fluid\Core\Parser\Exception $error
     * @return string
     */
    public function handleParserError(\TYPO3Fluid\Fluid\Core\Parser\Exception $error)
    {
        $this->addError($error);
        return '';
    }

    /**
     * @param \TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\ExpressionException $error
     * @return string
     */
    public function handleExpressionError(\TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\ExpressionException $error)
    {
        $this->addError($error);
        return '';
    }

    /**
     * @param \TYPO3Fluid\Fluid\Core\ViewHelper\Exception $error
     * @return string
     */
    public function handleViewHelperError(\TYPO3Fluid\Fluid\Core\ViewHelper\Exception $error)
    {
        $this->addError($error);
        return '';
    }

    /**
     * @param \TYPO3Fluid\Fluid\Core\Compiler\StopCompilingException $error
     * @return string
     */
    public function handleCompilerError(\TYPO3Fluid\Fluid\Core\Compiler\StopCompilingException $error)
    {
        $this->addError($error);
        return '';
    }

    /**
     * @param \TYPO3Fluid\Fluid\View\Exception $error
     * @return string
     */
    public function handleViewError(\TYPO3Fluid\Fluid\View\Exception $error)
    {
        $this->addError($error);
        return '';
    }
}
