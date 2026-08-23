<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Installer\Database\MigrationCollectionFactory;
use Contena\Core\TestBootstrapper;

/**
 * @internal
 */
#[CoversClass(MigrationCollectionFactory::class)]
class MigrationCollectionFactoryTest extends TestCase
{
    public function testGetMigrationCollectionLoader(): void
    {
        $factory = new MigrationCollectionFactory(new TestBootstrapper()->getProjectDir());
        $loader = $factory->getMigrationCollectionLoader(
            static::createStub(Connection::class)
        );

        static::assertArrayHasKey('core', $loader->collectAll());
        static::assertArrayHasKey('core.V6_8', $loader->collectAll());
    }
}
