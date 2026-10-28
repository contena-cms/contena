<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontPluginConfiguration::class)]
class StorefrontPluginConfigurationTest extends TestCase
{
    public function testAdditionalBundlesIsFalse(): void
    {
        $config = new StorefrontPluginConfiguration('name');

        static::assertFalse($config->hasAdditionalBundles());
    }

    public function testNameIsSet(): void
    {
        $config = new StorefrontPluginConfiguration('name');

        static::assertSame('name', $config->getTechnicalName());
    }
}
