<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Twig;

use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Framework\Twig\TokenParser\IconTokenParser;
use Twig\Extension\AbstractExtension;

#[Package('discovery')]
class IconExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct()
    {
    }

    public function getTokenParsers(): array
    {
        return [
            new IconTokenParser(),
        ];
    }
}
