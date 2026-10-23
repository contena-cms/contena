<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1773829000MigrateLineItemBlogStatesRuleCondition;

/**
 * @internal
 */
#[CoversClass(Migration1773829000MigrateLineItemBlogStatesRuleCondition::class)]
class Migration1773829000MigrateLineItemBlogStatesRuleConditionTest extends TestCase
{
    private Connection $connection;

    private string $ruleId;

    private string $digitalConditionId;

    private string $legacyConditionId;

    private string $noBlogStateConditionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->ruleId = Uuid::randomBytes();
        $this->digitalConditionId = Uuid::randomBytes();
        $this->legacyConditionId = Uuid::randomBytes();
        $this->noBlogStateConditionId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('rule_condition', ['id' => $this->digitalConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->legacyConditionId]);
        $this->connection->delete('rule_condition', ['id' => $this->noBlogStateConditionId]);
        $this->connection->delete('rule', ['id' => $this->ruleId]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773829000MigrateLineItemBlogStatesRuleCondition();

        static::assertSame(1773829000, $migration->getCreationTimestamp());
    }

    public function testUpdateIsNoOp(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'legacy blog states rule',
            'priority' => 1,
            'payload' => null,
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->digitalConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemBlogStates',
            'value' => json_encode(['operator' => '=', 'blogState' => 'is-download'], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829000MigrateLineItemBlogStatesRuleCondition();
        $migration->update($this->connection);

        // update() must not convert conditions, conversion was moved to updateDestructive()
        static::assertSame(
            'cartLineItemBlogStates',
            $this->connection->fetchOne(
                'SELECT `type` FROM `rule_condition` WHERE `id` = :id',
                ['id' => $this->digitalConditionId]
            )
        );
    }

    public function testMigration(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'legacy blog states rule',
            'priority' => 1,
            'payload' => '{"legacy":true}',
            'invalid' => 0,
            'module_types' => null,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->digitalConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemBlogStates',
            'value' => json_encode([
                'operator' => '=',
                'blogState' => 'is-download',
            ], \JSON_THROW_ON_ERROR),
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->legacyConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemBlogStates',
            'value' => json_encode([
                'operator' => '=',
                'blogState' => 'is-some-legacy-state',
            ], \JSON_THROW_ON_ERROR),
            'position' => 2,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('rule_condition', [
            'id' => $this->noBlogStateConditionId,
            'rule_id' => $this->ruleId,
            'parent_id' => null,
            'type' => 'cartLineItemBlogStates',
            'value' => json_encode([
                'operator' => '=',
                'foo' => 'bar',
            ], \JSON_THROW_ON_ERROR),
            'position' => 3,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829000MigrateLineItemBlogStatesRuleCondition();

        // make sure the migration is idempotent
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        $digitalCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->digitalConditionId]
        );

        static::assertIsArray($digitalCondition);
        static::assertSame('cartLineItemBlogType', $digitalCondition['type']);
        static::assertIsString($digitalCondition['value']);
        static::assertSame(
            [
                'operator' => '=',
                'blogType' => 'digital',
            ],
            json_decode($digitalCondition['value'], true, 512, \JSON_THROW_ON_ERROR)
        );

        $legacyCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->legacyConditionId]
        );

        static::assertIsArray($legacyCondition);
        static::assertSame('cartLineItemBlogStates', $legacyCondition['type']);

        $noBlogStateCondition = $this->connection->fetchAssociative(
            'SELECT `type`, `value` FROM `rule_condition` WHERE `id` = :id',
            ['id' => $this->noBlogStateConditionId]
        );

        static::assertIsArray($noBlogStateCondition);
        static::assertSame('cartLineItemBlogStates', $noBlogStateCondition['type']);

        static::assertSame(
            '{"legacy":true}',
            $this->connection->fetchOne(
                'SELECT `payload` FROM `rule` WHERE `id` = :id',
                ['id' => $this->ruleId]
            )
        );

        static::assertSame(
            '2',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `rule_condition` WHERE `rule_id` = :ruleId AND `type` = :type',
                ['ruleId' => $this->ruleId, 'type' => 'cartLineItemBlogStates']
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('rule.indexer', $indexers);
    }
}
