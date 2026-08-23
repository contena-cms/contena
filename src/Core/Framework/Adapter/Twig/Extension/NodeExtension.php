<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ExtendsTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\IncludeTokenParser;
use Contena\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser;
use Twig\Extension\AbstractExtension;
use Twig\TokenParser\TokenParserInterface;

/**
 * @internal
 */
class NodeExtension extends AbstractExtension
{
    public function __construct(
        private readonly TemplateFinderInterface $finder,
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

    public function getFinder(): TemplateFinderInterface
    {
        return $this->finder;
    }
}
