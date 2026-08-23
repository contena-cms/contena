<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\AbstractFlowLoader;
use Contena\Core\Content\Flow\Dispatching\FlowFactory;
use Contena\Core\Content\Flow\Dispatching\FlowLoader;
use Contena\Core\Content\Flow\Indexing\FlowIndexer;
use Contena\Core\Content\Flow\Indexing\FlowIndexingMessage;
use Contena\Core\Content\Flow\Indexing\FlowPayloadUpdater;
use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Contena\Core\Content\Rule\DataAbstractionLayer\RuleIndexingMessage;
use Contena\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TenantOwnedRuleFlowAggregateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string EVENT_NAME = 'tenant.rule-flow.matrix';

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Rule flow tenant A')->id;
        $this->tenantB = $this->createTenant('Rule flow tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];
    }

    public function testReadWriteAndCachedLoaderMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createRuleFlowAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        foreach ($this->contexts as $scope => $context) {
            foreach (['rule' => 'rule', 'rule_condition' => 'condition', 'flow' => 'flow', 'flow_sequence' => 'sequence'] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $this->assertMappingCount('rule_tag', 'ruleId', array_column($ids, 'rule'), $expectedCounts[$scope], $context, $scope);
            $this->assertLoadedRuleIds($ids, $expectedCounts[$scope], $context, $scope);
            $this->assertLoadedFlowIds($ids, $expectedCounts[$scope], $context, $scope);

            // The second read must use the same scope-specific cache without leaking another scope.
            $this->assertLoadedRuleIds($ids, $expectedCounts[$scope], $context, $scope . ' cached');
            $this->assertLoadedFlowIds($ids, $expectedCounts[$scope], $context, $scope . ' cached');
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $expectedTenantId = $expectedTenants[$scope];
            $this->assertStoredTenant('rule', 'id', $scopeIds['rule'], $expectedTenantId);
            $this->assertStoredTenant('rule_condition', 'id', $scopeIds['condition'], $expectedTenantId);
            $this->assertStoredTenant('rule_tag', 'rule_id', $scopeIds['rule'], $expectedTenantId);
            $this->assertStoredTenant('flow', 'id', $scopeIds['flow'], $expectedTenantId);
            $this->assertStoredTenant('flow_sequence', 'id', $scopeIds['sequence'], $expectedTenantId);
        }

        $tenantAIds = $ids['tenant-a'];
        $this->repository('rule')->update([['id' => $tenantAIds['rule'], 'name' => 'Updated tenant A rule']], $this->contexts['tenant-a']);
        $this->repository('rule_condition')->update([['id' => $tenantAIds['condition'], 'position' => 2]], $this->contexts['tenant-a']);
        $this->repository('flow')->update([['id' => $tenantAIds['flow'], 'description' => 'Updated tenant A flow']], $this->contexts['tenant-a']);
        $this->repository('flow_sequence')->update([['id' => $tenantAIds['sequence'], 'position' => 2]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            foreach ([
                ['rule', ['id' => $tenantAIds['rule'], 'name' => 'Rejected rule update']],
                ['rule_condition', ['id' => $tenantAIds['condition'], 'position' => 3]],
                ['flow', ['id' => $tenantAIds['flow'], 'description' => 'Rejected flow update']],
                ['flow_sequence', ['id' => $tenantAIds['sequence'], 'position' => 3]],
            ] as [$entityName, $payload]) {
                $this->assertWriteRejected(
                    fn () => $this->repository($entityName)->update([$payload], $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }

            $this->assertWriteRejected(
                fn () => $this->repository('rule_tag')->delete([[
                    'ruleId' => $tenantAIds['rule'],
                    'tagId' => $tenantAIds['tag'],
                ]], $this->contexts[$scope]),
                'Expected rule_tag write protection for ' . $scope,
            );
        }

        $this->assertCrossTenantReferencesAreRejected($ids);
        $this->assertFlowFactoryPreservesTenantScope();
    }

    public function testGlobalFullIndexingSwitchesIntoEachOwningScope(): void
    {
        $ids = [];
        foreach (['platform', 'tenant-a', 'tenant-b'] as $scope) {
            $ids[$scope] = $this->createRuleFlowAggregate('index-' . $scope, $this->contexts[$scope]);
        }

        $connection = static::getContainer()->get(Connection::class);
        $tenantAIds = $ids['tenant-a'];
        $connection->executeStatement(
            'UPDATE `rule` SET `payload` = NULL WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($tenantAIds['rule'])],
        );
        $connection->executeStatement(
            'UPDATE `flow` SET `payload` = NULL WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($tenantAIds['flow'])],
        );

        $globalContext = $this->contexts['global'];
        $rulePayloadUpdater = static::getContainer()->get(RulePayloadUpdater::class);
        $flowPayloadUpdater = static::getContainer()->get(FlowPayloadUpdater::class);
        static::assertSame([], $rulePayloadUpdater->update([$tenantAIds['rule']], $globalContext));
        static::assertSame([], $flowPayloadUpdater->update([$tenantAIds['flow']], $globalContext));
        static::assertNull($this->loadPayload('rule', $tenantAIds['rule']));
        static::assertNull($this->loadPayload('flow', $tenantAIds['flow']));

        $ruleMessage = new RuleIndexingMessage(array_column($ids, 'rule'), context: $globalContext);
        $ruleMessage->isFullIndexing = true;
        static::getContainer()->get(RuleIndexer::class)->handle($ruleMessage);

        $flowMessage = new FlowIndexingMessage(array_column($ids, 'flow'), context: $globalContext);
        $flowMessage->isFullIndexing = true;
        static::getContainer()->get(FlowIndexer::class)->handle($flowMessage);

        foreach ($ids as $scope => $scopeIds) {
            static::assertNotNull($this->loadPayload('rule', $scopeIds['rule']), 'Rule payload was not indexed for ' . $scope);
            static::assertNotNull($this->loadPayload('flow', $scopeIds['flow']), 'Flow payload was not indexed for ' . $scope);
        }
    }

    /**
     * @return array{rule: string, condition: string, tag: string, flow: string, sequence: string}
     */
    private function createRuleFlowAggregate(string $scope, Context $context): array
    {
        $ruleId = Uuid::randomHex();
        $conditionId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $flowId = Uuid::randomHex();
        $sequenceId = Uuid::randomHex();

        $this->repository('tag')->create([[
            'id' => $tagId,
            'name' => 'Rule flow tag ' . $scope . '-' . Uuid::randomHex(),
        ]], $context);
        $this->repository('rule')->create([[
            'id' => $ruleId,
            'name' => 'Rule flow rule ' . $scope,
            'priority' => 1,
            'conditions' => [[
                'id' => $conditionId,
                'type' => 'andContainer',
                'value' => [],
                'position' => 1,
            ]],
        ]], $context);
        $this->repository('rule_tag')->create([[
            'ruleId' => $ruleId,
            'tagId' => $tagId,
        ]], $context);
        $this->repository('flow')->create([[
            'id' => $flowId,
            'name' => 'Rule flow flow ' . $scope,
            'eventName' => self::EVENT_NAME,
            'active' => true,
            'priority' => 1,
            'sequences' => [[
                'id' => $sequenceId,
                'ruleId' => $ruleId,
                'position' => 1,
                'displayGroup' => 1,
                'trueCase' => false,
            ]],
        ]], $context);

        return [
            'rule' => $ruleId,
            'condition' => $conditionId,
            'tag' => $tagId,
            'flow' => $flowId,
            'sequence' => $sequenceId,
        ];
    }

    /**
     * @param array<string, array{rule: string, condition: string, tag: string, flow: string, sequence: string}> $ids
     */
    private function assertLoadedRuleIds(array $ids, int $expected, Context $context, string $scope): void
    {
        $loader = static::getContainer()->get(AbstractRuleLoader::class);
        static::assertInstanceOf(AbstractRuleLoader::class, $loader);
        $loadedIds = array_intersect($loader->load($context)->getIds(), array_column($ids, 'rule'));

        static::assertCount($expected, $loadedIds, 'Unexpected cached rule rows for ' . $scope);
    }

    /**
     * @param array<string, array{rule: string, condition: string, tag: string, flow: string, sequence: string}> $ids
     */
    private function assertLoadedFlowIds(array $ids, int $expected, Context $context, string $scope): void
    {
        $loader = static::getContainer()->get(FlowLoader::class);
        static::assertInstanceOf(AbstractFlowLoader::class, $loader);
        $holders = $loader->load($context)[self::EVENT_NAME] ?? [];
        $loadedIds = array_intersect(array_column($holders, 'id'), array_column($ids, 'flow'));

        static::assertCount($expected, $loadedIds, 'Unexpected cached flow rows for ' . $scope);
    }

    /**
     * @param array<string, array{rule: string, condition: string, tag: string, flow: string, sequence: string}> $ids
     */
    private function assertCrossTenantReferencesAreRejected(array $ids): void
    {
        $this->assertWriteRejected(
            fn () => $this->repository('rule_tag')->create([[
                'ruleId' => $ids['tenant-a']['rule'],
                'tagId' => $ids['tenant-b']['tag'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant rule tag referencing another tenant tag to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('flow_sequence')->create([[
                'id' => Uuid::randomHex(),
                'flowId' => $ids['tenant-a']['flow'],
                'ruleId' => $ids['tenant-b']['rule'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant flow sequence referencing another tenant rule to be rejected',
        );
    }

    private function assertFlowFactoryPreservesTenantScope(): void
    {
        $factory = static::getContainer()->get(FlowFactory::class);
        static::assertInstanceOf(FlowFactory::class, $factory);

        foreach ($this->contexts as $scope => $context) {
            $restoredContext = $factory->restore(self::EVENT_NAME, $context)->getContext();
            static::assertSame($context->getTenantId(), $restoredContext->getTenantId(), 'Unexpected restored tenant for ' . $scope);
            static::assertSame($context->hasGlobalTenantAccess(), $restoredContext->hasGlobalTenantAccess(), 'Unexpected restored global access for ' . $scope);
        }
    }

    /**
     * @param list<string> $ids
     */
    private function assertMappingCount(string $entityName, string $property, array $ids, int $expected, Context $context, string $scope): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($property, $ids));

        static::assertCount(
            $expected,
            $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
            'Unexpected ' . $entityName . ' rows for ' . $scope,
        );
    }

    private function assertStoredTenant(string $table, string $idColumn, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
    }

    private function loadPayload(string $table, string $id): mixed
    {
        return static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT `payload` FROM `%s` WHERE `id` = :id', $table),
            ['id' => Uuid::fromHexToBytes($id)],
        );
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        /** @var EntityRepository<EntityCollection<Entity>> $repository */
        $repository = static::getContainer()->get($entityName . '.repository');

        return $repository;
    }
}
