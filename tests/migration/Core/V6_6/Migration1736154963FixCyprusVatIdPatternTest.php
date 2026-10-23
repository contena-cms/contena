<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_6\Migration1736154963FixCyprusVatIdPattern;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1736154963FixCyprusVatIdPattern::class)]
class Migration1736154963FixCyprusVatIdPatternTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1736154963, (new Migration1736154963FixCyprusVatIdPattern())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->connection->update('country', ['vat_id_pattern' => 'CY\d{8}L'], ['iso' => 'CY']);

        $migration = new Migration1736154963FixCyprusVatIdPattern();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $result = $this->connection
            ->executeQuery('SELECT `vat_id_pattern` FROM country WHERE vat_id_pattern = :pat', ['pat' => 'CY\d{8}[A-Z]'])
            ->fetchAssociative();

        static::assertNotFalse($result);
        static::assertSame('CY\d{8}[A-Z]', $result['vat_id_pattern']);
    }
}
