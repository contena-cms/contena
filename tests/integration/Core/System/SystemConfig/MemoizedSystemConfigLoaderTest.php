<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Contena\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Contena\Core\System\SystemConfig\SystemConfigLoader;

/**
 * @internal
 */
#[Package('framework')]
class MemoizedSystemConfigLoaderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServiceDecorationChainPriority(): void
    {
        $service = static::getContainer()->get(SystemConfigLoader::class);

        static::assertInstanceOf(MemoizedSystemConfigLoader::class, $service);
        static::assertInstanceOf(ConfiguredSystemConfigLoader::class, $service->getDecorated());
        static::assertInstanceOf(CachedSystemConfigLoader::class, $service->getDecorated()->getDecorated());
        static::assertInstanceOf(SystemConfigLoader::class, $service->getDecorated()->getDecorated()->getDecorated());
    }
}
