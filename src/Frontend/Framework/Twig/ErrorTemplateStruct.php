<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

use Contena\Core\Framework\Struct\Struct;

class ErrorTemplateStruct extends Struct
{
    /**
     * @param array<string, \Throwable> $arguments
     */
    public function __construct(
        protected string $templateName = '',
        protected array $arguments = []
    ) {
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function setTemplateName(string $templateName): void
    {
        $this->templateName = $templateName;
    }

    /**
     * @return array<string, \Throwable>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array<string, \Throwable> $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function getApiAlias(): string
    {
        return 'twig_error_template';
    }

    public function isErrorPage(): bool
    {
        return true;
    }
}
