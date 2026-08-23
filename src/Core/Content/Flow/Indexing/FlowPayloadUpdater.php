<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Contena\Core\Content\Flow\Indexing\FlowBuilder\Sequence;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Uuid\Uuid;

class FlowPayloadUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly FlowBuilder $flowBuilder,
        private readonly CacheInvalidator $cacheInvalidator
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

        [$tenantCondition, $tenantParameters] = $this->getTenantCondition($context, '`flow`.');
        $listFlowSequence = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`flow`.`id`)) as array_key,
            LOWER(HEX(`flow`.`id`)) as `flow_id`,
            LOWER(HEX(`flow_sequence`.`id`)) as `sequence_id`,
            LOWER(HEX(`flow_sequence`.`parent_id`)) as `parent_id`,
            LOWER(HEX(`flow_sequence`.`rule_id`)) as `rule_id`,
            `flow_sequence`.`display_group` as `display_group`,
            `flow_sequence`.`position` as `position`,
            `flow_sequence`.`action_name` as `action_name`,
            `flow_sequence`.`config` as `config`,
            `flow_sequence`.`true_case` as `true_case`
            FROM `flow`
            LEFT JOIN `flow_sequence` ON `flow`.`id` = `flow_sequence`.`flow_id` AND `flow_sequence`.`tenant_id` <=> `flow`.`tenant_id`
            WHERE `flow`.`active` = 1
                AND (`flow_sequence`.`id` IS NULL OR (`flow_sequence`.`rule_id` IS NOT NULL OR `flow_sequence`.`action_name` IS NOT NULL))
                AND `flow`.`id` IN (:ids)
                AND ' . $tenantCondition,
            ['ids' => Uuid::fromHexToBytesList($ids), ...$tenantParameters],
            ['ids' => ArrayParameterType::BINARY]
        );

        $listFlowSequence = FetchModeHelper::group($listFlowSequence);

        [$updateTenantCondition, $updateTenantParameters] = $this->getTenantCondition($context);
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `flow` SET payload = :payload, invalid = :invalid WHERE `id` = :id AND ' . $updateTenantCondition)
        );

        $updated = [];
        foreach ($listFlowSequence as $flowId => $flowSequences) {
            $flowSequences = array_map(static fn (array $flowSequence) => Sequence::createFromDb($flowSequence), $flowSequences);
            usort($flowSequences, static function (Sequence $a, Sequence $b): int {
                $result = $a->displayGroup <=> $b->displayGroup;

                if ($result === 0) {
                    $result = $a->parentId <=> $b->parentId;
                }

                if ($result === 0) {
                    $result = $a->trueCase <=> $b->trueCase;
                }

                if ($result === 0) {
                    $result = $a->position <=> $b->position;
                }

                return $result;
            });

            $invalid = false;
            $serialized = null;

            try {
                $serialized = serialize($this->flowBuilder->build($flowId, $flowSequences));
            } catch (\Throwable) {
                $invalid = true;
            } finally {
                $update->execute([
                    'id' => Uuid::fromHexToBytes($flowId),
                    'payload' => $serialized,
                    'invalid' => (int) $invalid,
                    ...$updateTenantParameters,
                ]);
            }

            $updated[$flowId] = ['payload' => $serialized, 'invalid' => $invalid];
        }

        $this->cacheInvalidator->invalidate([CachedFlowLoader::KEY]);

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
     * @return array{string, array<string, string>}
     */
    private function getTenantCondition(Context $context, string $qualifier = ''): array
    {
        if ($context->getTenantId() === null) {
            return [$qualifier . '`tenant_id` IS NULL', []];
        }

        return [$qualifier . '`tenant_id` = :tenantId', ['tenantId' => Uuid::fromHexToBytes($context->getTenantId())]];
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
            'SELECT LOWER(HEX(`id`)) AS `id`, LOWER(HEX(`tenant_id`)) AS `tenant_id` FROM `flow` WHERE `id` IN (:ids)',
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
