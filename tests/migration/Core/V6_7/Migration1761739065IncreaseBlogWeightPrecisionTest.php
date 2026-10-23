<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Migration\V6_7\Migration1761739065IncreaseBlogWeightPrecision;

/**
 * @internal
 */
#[CoversClass(Migration1761739065IncreaseBlogWeightPrecision::class)]
class Migration1761739065IncreaseBlogWeightPrecisionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1761739065, (new Migration1761739065IncreaseBlogWeightPrecision())->getCreationTimestamp());
    }

    public function testUpdateIncreasesPrecision(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        try {
            $this->setBlogWeightPrecision($connection, 'DECIMAL(10,3) UNSIGNED NULL');

            $migration = new Migration1761739065IncreaseBlogWeightPrecision();
            $migration->update($connection);
            $migration->update($connection);

            static::assertSame(
                'decimal(15,6) unsigned',
                $this->getBlogWeightColumnType($connection)
            );
        } finally {
            $this->setBlogWeightPrecision($connection, 'DECIMAL(15,6) UNSIGNED NULL');
        }
    }

    public function testUpdateSkipsWhenPrecisionAlreadyExtended(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        try {
            $this->setBlogWeightPrecision($connection, 'DECIMAL(20,8) UNSIGNED NULL');

            $migration = new Migration1761739065IncreaseBlogWeightPrecision();
            $migration->update($connection);

            static::assertSame(
                'decimal(20,8) unsigned',
                $this->getBlogWeightColumnType($connection)
            );
        } finally {
            $this->setBlogWeightPrecision($connection, 'DECIMAL(15,6) UNSIGNED NULL');
        }
    }

    private function setBlogWeightPrecision(Connection $connection, string $definition): void
    {
        $connection->executeStatement(
            \sprintf('ALTER TABLE `blog` MODIFY `weight` %s', $definition)
        );
    }

    private function getBlogWeightColumnType(Connection $connection): string
    {
        $columnTypeQuery = <<<'SQL'
            SELECT LOWER(COLUMN_TYPE)
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = 'blog'
              AND column_name = 'weight';
        SQL;

        $type = $connection->fetchOne($columnTypeQuery, ['schema' => $connection->getDatabase()]);

        return \is_string($type) ? $type : '';
    }
}
