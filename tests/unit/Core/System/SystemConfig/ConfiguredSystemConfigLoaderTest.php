<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Contena\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Contena\Core\System\SystemConfig\SymfonySystemConfigService;

/**
 * @internal
 */
#[CoversClass(ConfiguredSystemConfigLoader::class)]
class ConfiguredSystemConfigLoaderTest extends TestCase
{
    public function testDecoration(): void
    {
        $configLoader = $this->createMock(AbstractSystemConfigLoader::class);

        $config = new SymfonySystemConfigService(['default' => ['test.key' => 'true']]);

        $decorator = new ConfiguredSystemConfigLoader($configLoader, $config);

        $configLoader->expects($this->once())
            ->method('load')
            ->willReturn(['test' => ['key' => 'false']]);

        static::assertSame(['test' => ['key' => 'true']], $decorator->load(null));
    }
}
