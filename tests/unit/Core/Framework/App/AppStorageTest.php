<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(AppStorage::class)]
class AppStorageTest extends TestCase
{
    public function testFindById(): void
    {
        $app = AppFixture::createAppEntity(id: 'app-id');
        $repository = StaticEntityRepository::of(AppCollection::class, [[$app]]);

        static::assertSame($app, (new AppStorage($repository))->findById('app-id', Context::createDefaultContext()));
    }

    public function testFindByName(): void
    {
        $app = AppFixture::createAppEntity(name: 'SwagTest');
        $repository = StaticEntityRepository::of(AppCollection::class, [[$app]]);

        static::assertSame($app, (new AppStorage($repository))->findByName('SwagTest', Context::createDefaultContext()));
    }

    public function testReturnsNullIfAppDoesNotExist(): void
    {
        $repository = StaticEntityRepository::of(AppCollection::class, [
            [],
            [],
        ]);
        $appStorage = new AppStorage($repository);

        static::assertNull($appStorage->findById('missing-id', Context::createDefaultContext()));
        static::assertNull($appStorage->findByName('missing-name', Context::createDefaultContext()));
    }

    public function testFindAll(): void
    {
        $apps = new AppCollection([AppFixture::createAppEntity()]);
        $repository = StaticEntityRepository::of(AppCollection::class, [$apps]);

        static::assertSame($apps, (new AppStorage($repository))->findAll(Context::createDefaultContext()));
    }

    public function testFindAllByNameOrLabel(): void
    {
        $apps = new AppCollection([AppFixture::createAppEntity()]);
        $repository = StaticEntityRepository::of(AppCollection::class, [$apps]);

        static::assertSame($apps, (new AppStorage($repository))->findAllWithNameOrLabel('test-app', Context::createDefaultContext()));
    }
}
