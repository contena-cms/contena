<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\FrontendPluginConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;

/**
 * @internal
 */
#[CoversClass(FrontendPluginConfiguration::class)]
class FrontendPluginConfigurationTest extends TestCase
{
    public function testAssetName(): void
    {
        $config = new FrontendPluginConfiguration('ContenaPlugin');
        static::assertSame('contena-plugin', $config->getAssetName());
    }
}
