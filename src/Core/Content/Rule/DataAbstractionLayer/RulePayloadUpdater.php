<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\DataAbstractionLayer;

use Contena\Core\Content\Rule\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Contena\Core\Content\Rule\RuleException;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\Event\AppScriptConditionEvents;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\ContainerInterface;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\ScriptRule;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class RulePayloadUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RuleConditionRegistry $ruleConditionRegistry,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [AppScriptConditionEvents::APP_SCRIPT_CONDITION_WRITTEN_EVENT => 'updatePayloads'];
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
            'SELECT LOWER(HEX(rc.rule_id)) AS array_key, rc.id, rc.rule_id, rc.parent_id, rc.type, rc.value, rc.position,
                rs.script, rs.identifier, rs.updated_at AS lastModified
             FROM rule_condition rc
             LEFT JOIN app_script_condition rs ON rc.script_id = rs.id AND rs.active = 1
             WHERE rc.rule_id IN (:ids) AND ' . str_replace('`tenant_id`', 'rc.`tenant_id`', $tenantCondition) . ' ORDER BY rc.rule_id, rc.position',
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

    public function updatePayloads(EntityWrittenEvent $event): void
    {
        $scriptIds = array_values(array_filter($event->getIds(), 'is_string'));
        if ($scriptIds === []) {
            return;
        }

        $ruleIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT rc.rule_id FROM rule_condition rc INNER JOIN app_script_condition rs ON rc.script_id = rs.id WHERE rs.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($scriptIds)],
            ['ids' => ArrayParameterType::BINARY],
        );
        if ($ruleIds === []) {
            return;
        }

        $this->updateAllScopes(array_values(Uuid::fromBytesToHexList($ruleIds)));
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
            if ($rule instanceof ScriptRule) {
                $rule->assign([
                    'script' => $condition['script'] ?? '',
                    'lastModified' => $condition['lastModified'] !== null ? new \DateTimeImmutable((string) $condition['lastModified']) : null,
                    'identifier' => $condition['identifier'] ?? null,
                    'values' => $condition['value'] !== null ? json_decode((string) $condition['value'], true, 512, \JSON_THROW_ON_ERROR) : [],
                ]);
                $nested[] = $rule;

                continue;
            }

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
