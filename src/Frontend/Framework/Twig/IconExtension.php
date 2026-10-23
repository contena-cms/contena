<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

use Contena\Frontend\Framework\Twig\TokenParser\IconTokenParser;
use Twig\Extension\AbstractExtension;

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
