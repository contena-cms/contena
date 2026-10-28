<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ThemeSalesChannel>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ThemeSalesChannelCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ThemeSalesChannel::class;
    }
}
