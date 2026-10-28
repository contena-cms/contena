<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Filter;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class ReplaceRecursiveFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('replace_recursive', $this->replaceRecursive(...)),
        ];
    }

    /**
     * @param array<mixed> ...$params
     *
     * @return array<mixed>
     */
    public function replaceRecursive(array ...$params): array
    {
        return array_replace_recursive(...$params);
    }
}
