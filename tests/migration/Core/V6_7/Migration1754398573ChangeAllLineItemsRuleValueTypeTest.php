<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Migration\V6_7\Migration1754398573ChangeAllLineItemsRuleValueType;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Migration1754398573ChangeAllLineItemsRuleValueType::class)]
class Migration1754398573ChangeAllLineItemsRuleValueTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        $this->addTestConditions();

        $migration = new Migration1754398573ChangeAllLineItemsRuleValueType();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1754398573, (new Migration1754398573ChangeAllLineItemsRuleValueType())->getCreationTimestamp());
    }

    public function testShouldUpdateAllLineItemsRuleValue(): void
    {
        static::assertSame(6, $this->getConditionCount());

        $conditionValues = $this->getConditionValues();
        static::assertCount(6, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-1'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-2'), $conditionValues);

        static::assertSame(
            ['types' => ['blog']],
            $conditionValues[$this->ids->getBytes('rule-condition-1')],
        );

        static::assertSame(
            ['types' => ['something']],
            $conditionValues[$this->ids->getBytes('rule-condition-2')],
        );
    }

    public function testShouldNotUpdateConditionsWithOtherTypes(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(6, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-3'), $conditionValues);

        static::assertSame(
            ['type' => 'blog'],
            $conditionValues[$this->ids->getBytes('rule-condition-3')],
        );
    }

    public function testShouldNotUpdateConditionsWithNoMatchingValue(): void
    {
        $conditionValues = $this->getConditionValues();
        static::assertCount(6, $conditionValues);

        static::assertArrayHasKey($this->ids->getBytes('rule-condition-4'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-5'), $conditionValues);
        static::assertArrayHasKey($this->ids->getBytes('rule-condition-6'), $conditionValues);

        static::assertSame(
            ['something' => 'blog'],
            $conditionValues[$this->ids->getBytes('rule-condition-4')],
        );

        static::assertSame(
            ['type' => 1],
            $conditionValues[$this->ids->getBytes('rule-condition-5')],
        );

        static::assertSame(
            [],
            $conditionValues[$this->ids->getBytes('rule-condition-6')],
        );
    }

    private function getConditionCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `rule_condition` WHERE `rule_id` = :ruleId',
            ['ruleId' => $this->ids->getBytes('rule')]
        );
    }

    /**
     * @return mixed[]
     */
    private function getConditionValues(): array
    {
        return array_map(
            static fn (string $json) => json_decode($json, true),
            static::getContainer()->get(Connection::class)->fetchAllKeyValue(
                'SELECT `id`, `value` FROM `rule_condition` WHERE `rule_id` = :ruleId',
                ['ruleId' => $this->ids->getBytes('rule')]
            )
        );
    }

    private function addTestConditions(): void
    {
        static::getContainer()->get(Connection::class)->insert('rule', [
            'id' => $this->ids->getBytes('rule'),
            'name' => 'some rule',
            'priority' => 1,
            'payload' => 'some value',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // first target
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-1'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"type":"blog"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // second target
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-2'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"type":"something"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // other condition type
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-3'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'userDefaultPaymentMethod',
            'value' => '{"type":"blog"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // no matching condition value
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-4'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"something":"blog"}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // no matching condition value type
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-5'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '{"type":1}',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // empty condition value
        static::getContainer()->get(Connection::class)->insert('rule_condition', [
            'id' => $this->ids->getBytes('rule-condition-6'),
            'rule_id' => $this->ids->getBytes('rule'),
            'type' => 'allLineItemsContainer',
            'value' => '[]',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
