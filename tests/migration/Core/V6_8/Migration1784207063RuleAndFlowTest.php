<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207063CreateRule;
use Contena\Core\Migration\V6_8\Migration1784207064CreateRuleCondition;
use Contena\Core\Migration\V6_8\Migration1784207065CreateRuleTag;
use Contena\Core\Migration\V6_8\Migration1784207066CreateFlow;
use Contena\Core\Migration\V6_8\Migration1784207067CreateFlowSequence;
use Contena\Core\Migration\V6_8\Migration1784207068CreateFlowTemplate;

/**
 * @internal
 */
#[CoversClass(Migration1784207063CreateRule::class)]
#[CoversClass(Migration1784207064CreateRuleCondition::class)]
#[CoversClass(Migration1784207065CreateRuleTag::class)]
#[CoversClass(Migration1784207066CreateFlow::class)]
#[CoversClass(Migration1784207067CreateFlowSequence::class)]
#[CoversClass(Migration1784207068CreateFlowTemplate::class)]
class Migration1784207063RuleAndFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesRuleAndFlowTablesIdempotently(): void
    {
        $migrations = [
            new Migration1784207063CreateRule(),
            new Migration1784207064CreateRuleCondition(),
            new Migration1784207065CreateRuleTag(),
            new Migration1784207066CreateFlow(),
            new Migration1784207067CreateFlowSequence(),
            new Migration1784207068CreateFlowTemplate(),
        ];

        foreach ($migrations as $migration) {
            $migration->update($this->connection);
            $migration->update($this->connection);
        }

        foreach (['id', 'name', 'priority', 'payload', 'invalid', 'areas'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'rule', $column), 'rule.' . $column);
        }

        foreach (['id', 'type', 'rule_id', 'parent_id', 'value', 'position'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'rule_condition', $column), 'rule_condition.' . $column);
        }

        foreach (['id', 'name', 'event_name', 'priority', 'payload', 'invalid', 'active'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'flow', $column), 'flow.' . $column);
        }

        foreach (['id', 'flow_id', 'parent_id', 'rule_id', 'action_name', 'config', 'display_group', 'true_case'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'flow_sequence', $column), 'flow_sequence.' . $column);
        }

        foreach (['id', 'name', 'config'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'flow_template', $column), 'flow_template.' . $column);
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'rule_condition', 'idx.rule_condition.rule_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'flow', 'idx.flow.event_name'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'flow_sequence', 'idx.flow_sequence.flow_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'rule_condition', 'fk.rule_condition.rule_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'flow_sequence', 'fk.flow_sequence.flow_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'flow_sequence', 'fk.flow_sequence.rule_id'));
    }
}
