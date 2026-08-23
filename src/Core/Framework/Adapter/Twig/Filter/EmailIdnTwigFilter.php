<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Filter;

use Contena\Core\Framework\Validation\EmailIdnConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
class EmailIdnTwigFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('decodeIdnEmail', EmailIdnConverter::decode(...)),
            new TwigFilter('encodeIdnEmail', EmailIdnConverter::encode(...)),
        ];
    }
}
