<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_6\Migration1673249981MigrateIsNewUserRule;

/**
 * @internal
 */
#[CoversClass(Migration1673249981MigrateIsNewUserRule::class)]
class Migration1673249981MigrateIsNewUserRuleTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;


    /**
     * @var array<string, mixed>
     */
    private array $testRule;

    /**
     * @var array<string, mixed>
     */
    private array $testCondition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1673249981MigrateIsNewUserRule();

        $this->testRule = [
            'id' => Uuid::randomBytes(),
            'name' => 'testMigrateIsNewUserRule',
            'priority' => 1,
            'payload' => 'someValue',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $this->testCondition = [
            'id' => Uuid::randomBytes(),
            'rule_id' => $this->testRule['id'],
            'type' => 'userIsNewUser',
            'value' => '{"isNew":true}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1673249981, (new Migration1673249981MigrateIsNewUserRule())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->addTestConditions();
        static::assertGreaterThanOrEqual(1, $this->getIsNewUserConditions());

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);
        static::assertCount(0, $this->getIsNewUserConditions());
        static::assertNull($this->getTestRule()['payload'], 'the migrated rule payload should be empty');
        $value = json_decode((string) $this->getDaysSinceFirstLoginConditions()['value'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('=', $value['operator']);
        static::assertSame(0, $value['daysPassed']);

        $this->removeTestConditions();
    }

    /**
     * @return array<string, mixed>[]
     */
    private function getIsNewUserConditions(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM rule_condition WHERE type = "userIsNewUser"');
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getDaysSinceFirstLoginConditions(): array
    {
        return (array) $this->connection->fetchAssociative('SELECT * FROM rule_condition WHERE type = "userDaysSinceFirstLogin"');
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getTestRule(): array
    {
        return (array) $this->connection->fetchAssociative('SELECT * FROM rule WHERE id = :id', [
            'id' => $this->testRule['id'],
        ]);
    }

    private function addTestConditions(): void
    {
        $this->connection->insert('rule', $this->testRule);
        $this->connection->insert('rule_condition', $this->testCondition);
    }

    private function removeTestConditions(): void
    {
        $this->connection->delete('rule_condition', [
            'id' => $this->testCondition['id'],
        ]);
        $this->connection->delete('rule', [
            'id' => $this->testRule['id'],
        ]);
    }
}
