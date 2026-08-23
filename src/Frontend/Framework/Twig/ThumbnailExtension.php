<?php
declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Frontend\Framework\Twig\TokenParser\ThumbnailTokenParser;
use Twig\Extension\AbstractExtension;

class ThumbnailExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(private readonly TemplateFinder $finder)
    {
    }

    public function getTokenParsers(): array
    {
        return [
            new ThumbnailTokenParser(),
        ];
    }

    public function getFinder(): TemplateFinder
    {
        return $this->finder;
    }
}
