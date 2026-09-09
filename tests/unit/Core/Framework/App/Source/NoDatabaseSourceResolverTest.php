<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Source;

use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Source\NoDatabaseSourceResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NoDatabaseSourceResolver::class)]
class NoDatabaseSourceResolverTest extends TestCase
{
    public function testExceptionIsThrownIfAppNotInActiveApps(): void
    {
        static::expectExceptionObject(AppException::notFoundByField('TestApp', 'name'));

        $activeAppsLoader = static::createStub(ActiveAppsLoader::class);
        $activeAppsLoader->method('getActiveApps')->willReturn([]);

        $resolver = new NoDatabaseSourceResolver($activeAppsLoader);
        $resolver->filesystem('TestApp');
    }

    public function testFilesystemForActiveAppUsesPath(): void
    {
        $activeAppsLoader = static::createStub(ActiveAppsLoader::class);
        $activeAppsLoader->method('getActiveApps')->willReturn([
            [
                'name' => 'TestApp',
                'path' => '/path/to/app',
            ],
        ]);

        $resolver = new NoDatabaseSourceResolver($activeAppsLoader);
        static::assertSame('/path/to/app', $resolver->filesystem('TestApp')->location);
    }
}
