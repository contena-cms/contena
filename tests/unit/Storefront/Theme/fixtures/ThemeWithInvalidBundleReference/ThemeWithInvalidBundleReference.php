<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\fixtures\ThemeWithInvalidBundleReference;

use Contena\Core\Framework\Bundle;
use Contena\Storefront\Framework\ThemeInterface;
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
