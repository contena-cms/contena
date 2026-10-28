<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\StorefrontPluginConfiguration;

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
    public function testAssetName(): void
    {
        $config = new StorefrontPluginConfiguration('SwagPayPal');
        static::assertSame('swag-pay-pal', $config->getAssetName());
    }
}
