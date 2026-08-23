<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ThemeChannel>
 */
class ThemeChannelCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ThemeChannel::class;
    }
}
