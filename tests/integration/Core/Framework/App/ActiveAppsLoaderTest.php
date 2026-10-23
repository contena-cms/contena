<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\ActiveAppsLoader;

/**
 * @internal
 */
class ActiveAppsLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private ActiveAppsLoader $activeAppsLoader;

    protected function setUp(): void
    {
        $this->activeAppsLoader = static::getContainer()->get(ActiveAppsLoader::class);
    }

    public function testGetActiveAppsWithActiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test');

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(1, $activeApps);
        static::assertSame('test', $activeApps[0]['name']);
    }

    public function testGetActiveAppsWithInactiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test', false);

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(0, $activeApps);
    }
}
