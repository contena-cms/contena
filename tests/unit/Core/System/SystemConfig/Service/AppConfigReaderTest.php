<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\Service\AppConfigReader;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Contena\Core\Test\Stub\App\StaticSourceResolver;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(AppConfigReader::class)]
class AppConfigReaderTest extends TestCase
{
    public function testReadConfigFromApp(): void
    {
        $app = (new AppEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'TestApp']);

        $fs = new StaticFilesystem([
            'Resources/config/config.xml' => 'config',
        ]);
        $sourceResolver = new StaticSourceResolver(['TestApp' => $fs]);

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->expects($this->once())
            ->method('read')
            ->with('/app-root/Resources/config/config.xml')
            ->willReturn([
                'config1' => 'value',
            ]);

        $appConfigReader = new AppConfigReader($sourceResolver, $configReader);
        static::assertSame(
            [
                'config1' => 'value',
            ],
            $appConfigReader->read($app)
        );
    }

    public function testReadConfigFromAppWhenItHasNone(): void
    {
        $app = (new AppEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'TestApp']);

        $fs = new StaticFilesystem();

        $sourceResolver = new StaticSourceResolver(['TestApp' => $fs]);

        $configReader = $this->createMock(ConfigReader::class);
        $configReader->expects($this->never())->method('read');

        $appConfigReader = new AppConfigReader($sourceResolver, $configReader);
        static::assertNull($appConfigReader->read($app));
    }
}
