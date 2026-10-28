<?php
declare(strict_types=1);

namespace Contena\Storefront\Framework\Twig;

use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;

#[Package('discovery')]
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
        ];
    }

    public function getFinder(): TemplateFinder
    {
        return $this->finder;
    }
}
