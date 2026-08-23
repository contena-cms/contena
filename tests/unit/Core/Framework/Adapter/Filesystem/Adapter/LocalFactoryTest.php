<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;

/**
 * @internal
 */
#[CoversClass(LocalFactory::class)]
class LocalFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $factory = new LocalFactory();
        static::assertSame('local', $factory->getType());

        $adapter = $factory->create([
            'root' => __DIR__,
        ]);

        static::assertInstanceOf(LocalFilesystemAdapter::class, $adapter);
    }
}
