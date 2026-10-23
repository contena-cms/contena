<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_7\Migration1763544592UpdateGroupRegistrationMailTemplates;

/**
 * @internal
 */
#[CoversClass(Migration1763544592UpdateGroupRegistrationMailTemplates::class)]
class Migration1763544592UpdateGroupRegistrationMailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1763544592, (new Migration1763544592UpdateGroupRegistrationMailTemplates())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $migration = new Migration1763544592UpdateGroupRegistrationMailTemplates();

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
