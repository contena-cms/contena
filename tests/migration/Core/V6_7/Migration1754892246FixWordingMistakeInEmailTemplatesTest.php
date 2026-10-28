<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_7\Migration1754892246FixWordingMistakeInEmailTemplates;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1754892246FixWordingMistakeInEmailTemplates::class)]
class Migration1754892246FixWordingMistakeInEmailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1754892246, (new Migration1754892246FixWordingMistakeInEmailTemplates())->getCreationTimestamp());
    }

    public function testMigrationOfUnmodifiedTranslation(): void
    {
        $migration = new Migration1754892246FixWordingMistakeInEmailTemplates();

        $error = [];
        try {
            $migration->update($this->connection);
            $migration->update($this->connection);
        } catch (\Throwable $e) {
            $error[] = $e->getMessage();
        }

        static::assertCount(0, $error, print_r($error, true));
    }
}
