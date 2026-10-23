<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;

/**
 * @internal
 */
#[CoversClass(FrontendPluginConfiguration::class)]
class FrontendPluginConfigurationTest extends TestCase
{
    public function testAdditionalBundlesIsFalse(): void
    {
        $config = new FrontendPluginConfiguration('name');

        static::assertFalse($config->hasAdditionalBundles());
    }

    public function testNameIsSet(): void
    {
        $config = new FrontendPluginConfiguration('name');

        static::assertSame('name', $config->getTechnicalName());
    }
}
