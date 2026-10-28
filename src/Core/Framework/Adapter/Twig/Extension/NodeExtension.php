<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ExtendsTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\IncludeTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Contena\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TokenParser\TokenParserInterface;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class NodeExtension extends AbstractExtension
{
    /**
     * @internal
     */
    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'finder', newType: TemplateFinderInterface::class)]
    public function __construct(
        private readonly TemplateFinder $finder,
        private readonly TemplateScopeDetector $templateScopeDetector,
    ) {
    }

    /**
     * @return TokenParserInterface[]
     */
    public function getTokenParsers(): array
    {
        return [
            new ExtendsTokenParser($this->finder, $this->templateScopeDetector),
            new IncludeTokenParser($this->finder),
            new ReturnNodeTokenParser(),
        ];
    }

    public function getFinder(): TemplateFinder
    {
        return $this->finder;
    }
}
