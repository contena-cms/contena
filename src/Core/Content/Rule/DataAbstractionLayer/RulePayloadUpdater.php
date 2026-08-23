<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Rule\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Contena\Core\Content\Rule\RuleException;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\ContainerInterface;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class RulePayloadUpdater
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RuleConditionRegistry $ruleConditionRegistry,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{payload: string|null, invalid: bool}>
     */
    public function update(array $ids, Context $context): array
    {
        if ($ids === []) {
            return [];
        }

        [$tenantCondition, $tenantParameters] = $this->getTenantCondition($context);
        $eligibleIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `rule` WHERE `id` IN (:ids) AND ' . $tenantCondition,
            ['ids' => Uuid::fromHexToBytesList($ids), ...$tenantParameters],
            ['ids' => ArrayParameterType::BINARY],
        );
        $eligibleIds = array_values(array_filter($eligibleIds, 'is_string'));
        if ($eligibleIds === []) {
            return [];
        }

        $conditions = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(rule_id)) AS array_key, id, rule_id, parent_id, type, value, position FROM rule_condition WHERE rule_id IN (:ids) AND ' . $tenantCondition . ' ORDER BY rule_id, position',
            ['ids' => Uuid::fromHexToBytesList($eligibleIds), ...$tenantParameters],
            ['ids' => ArrayParameterType::BINARY],
        );

        /** @var array<string, list<array<string, string|null>>> $rules */
        $rules = FetchModeHelper::group($conditions);
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $query = new RetryableQuery($this->connection, $this->connection->prepare('UPDATE `rule` SET payload = :payload, invalid = :invalid, updated_at = :updatedAt WHERE id = :id AND ' . $tenantCondition));

        $updated = [];
        foreach ($eligibleIds as $id) {
            $serialized = null;
            $invalid = false;

            try {
                $serialized = serialize(new AndRule($this->buildNested($rules[$id] ?? [], null)));
            } catch (ConditionTypeNotFound) {
                $invalid = true;
            }

            $query->execute([
                'id' => Uuid::fromHexToBytes($id),
                'payload' => $serialized,
                'invalid' => (int) $invalid,
                'updatedAt' => $now,
                ...$tenantParameters,
            ]);
            $updated[$id] = ['payload' => $serialized, 'invalid' => $invalid];
        }

        return $updated;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array{context: Context, ids: list<string>}>
     */
    public function updateAllScopes(array $ids): array
    {
        $batches = [];
        foreach ($this->groupIdsByTenant($ids) as $tenantId => $scopeIds) {
            $context = $tenantId === 'platform'
                ? Context::createDefaultContext()
                : Context::createTenantContext($tenantId);
            $updatedIds = array_keys($this->update($scopeIds, $context));
            if ($updatedIds !== []) {
                $batches[] = ['context' => $context, 'ids' => $updatedIds];
            }
        }

        return $batches;
    }

    /**
     * @param list<array<string, mixed>> $conditions
     *
     * @return list<Rule>
     */
    private function buildNested(array $conditions, ?string $parentId): array
    {
        $nested = [];
        foreach ($conditions as $condition) {
            if ($condition['parent_id'] !== $parentId) {
                continue;
            }

            $type = $condition['type'];
            if (!\is_string($type) || !$this->ruleConditionRegistry->has($type)) {
                throw RuleException::conditionTypeNotFound((string) $type);
            }

            $class = $this->ruleConditionRegistry->getRuleClass($type);
            $rule = new $class();
            if ($condition['value'] !== null) {
                $value = json_decode((string) $condition['value'], true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($value)) {
                    $rule->assign($value);
                }
            }

            if ($rule instanceof ContainerInterface) {
                foreach ($this->buildNested($conditions, $condition['id']) as $child) {
                    $rule->addRule($child);
                }
            }

            $nested[] = $rule;
        }

        return $nested;
    }

    /**
     * @return array{string, array<string, string>}
     */
    private function getTenantCondition(Context $context): array
    {
        if ($context->getTenantId() === null) {
            return ['`tenant_id` IS NULL', []];
        }

        return ['`tenant_id` = :tenantId', ['tenantId' => Uuid::fromHexToBytes($context->getTenantId())]];
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, list<string>>
     */
    private function groupIdsByTenant(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`tenant_id`)) AS `tenant_id` FROM `rule` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $grouped = [];
        foreach ($rows as $row) {
            if (!\is_string($row['id'])) {
                continue;
            }

            $tenantId = \is_string($row['tenant_id']) ? $row['tenant_id'] : 'platform';
            $grouped[$tenantId][] = $row['id'];
        }

        return $grouped;
    }
}
