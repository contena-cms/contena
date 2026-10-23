<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeWithInvalidBundleReference;

use Contena\Core\Framework\Bundle;
use Contena\Frontend\Framework\ThemeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class ThemeWithInvalidBundleReference extends Bundle implements ThemeInterface
{
    public function getThemeName(): string
    {
        return 'ThemeWithInvalidBundleReference';
    }

    public function getPath(): string
    {
        return __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
    }
}
