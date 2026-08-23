<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SeoUrlPersister
{
    /**
     * @internal
     *
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string> $foreignKeys
     * @param iterable<array<string, mixed>|SeoUrlEntity> $seoUrls
     */
    public function updateSeoUrls(Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, ChannelEntity $channel): void
    {
        $this->doUpdateSeoUrls($context, $routeName, $foreignKeys, $seoUrls, $channel, false);
    }

    /**
     * Like {@see self::updateSeoUrls()} but bypasses the write-protection guard
     * that normally keeps automatic template regeneration from overwriting
     * manually modified (`isModified = true`) SEO URLs.
     *
     * Intended for explicit admin/API updates where the user wants to edit or
     * reset a manually modified SEO URL; must not be used for automatic
     * template regeneration pipelines (indexers, subscribers on other entities).
     *
     * @param array<string> $foreignKeys
     * @param iterable<array<string, mixed>|SeoUrlEntity> $seoUrls
     */
    public function forceUpdateSeoUrls(Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, ChannelEntity $channel): void
    {
        $this->doUpdateSeoUrls($context, $routeName, $foreignKeys, $seoUrls, $channel, true);
    }

    /**
     * @param array<string> $foreignKeys
     * @param iterable<array<string, mixed>|SeoUrlEntity> $seoUrls
     */
    private function doUpdateSeoUrls(Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, ChannelEntity $channel, bool $overwrite): void
    {
        $languageId = $context->getLanguageId();
        $tenantId = $channel->getTenantId() ?? $context->getTenantId();
        $canonicals = $this->findCanonicalPaths($routeName, $languageId, $foreignKeys, $tenantId);
        $dateTime = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $table = $this->seoUrlRepository->getDefinition()->getEntityName();
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, false, false);
        foreach (['foreign_key', 'path_info', 'seo_path_info', 'route_name', 'is_canonical', 'is_modified', 'is_deleted'] as $updateField) {
            $insertQuery->addUpdateFieldOnDuplicateKey($table, $updateField);
        }

        $updatedFks = [];
        $obsoleted = [];

        $processed = [];

        $seoPathInfos = [];

        $channelId = $channel->getId();
        $updates = [];
        foreach ($seoUrls as $seoUrl) {
            if ($seoUrl instanceof SeoUrlEntity) {
                $seoUrl = $seoUrl->jsonSerialize();
            }
            $updates[] = $seoUrl;

            $fk = $seoUrl['foreignKey'];
            $channelId = $seoUrl['channelId'] ??= null;

            // skip duplicates
            if (isset($processed[$fk][$channelId])) {
                continue;
            }

            if (!isset($processed[$fk])) {
                $processed[$fk] = [];
            }
            $processed[$fk][$channelId] = true;

            $updatedFks[] = $fk;

            if (($seoUrl['error'] ?? '') !== '') {
                continue;
            }
            $existing = $canonicals[$fk][$channelId] ?? null;

            if ($existing !== null) {
                // entity has override or does not change
                if ($this->skipUpdate($existing, $seoUrl, $overwrite)) {
                    continue;
                }
                $obsoleted[] = $existing['id'];
            }

            // Generated SEO URLs bypass the DAL write validator, so filter
            // sequences that are not URL-allowed here (stray `%`, `#`, `\`,
            // control chars) rather than rejecting the batch. Valid
            // percent-escapes emitted by rawurlencode for non-ASCII slug
            // configs are preserved. See #13796.
            $seoPathInfo = ValidSeoPathInfo::sanitize(ltrim((string) $seoUrl['seoPathInfo'], '/'));

            $seoPathInfos[] = $seoPathInfo;

            $insert = [];
            $insert['id'] = Uuid::randomBytes();

            if ($channelId) {
                $insert['channel_id'] = Uuid::fromHexToBytes($channelId);
            }
            $insert['tenant_id'] = $tenantId !== null ? Uuid::fromHexToBytes($tenantId) : null;
            $insert['language_id'] = Uuid::fromHexToBytes($languageId);
            $insert['foreign_key'] = Uuid::fromHexToBytes($fk);

            $insert['path_info'] = $seoUrl['pathInfo'];
            $insert['seo_path_info'] = $seoPathInfo;

            $insert['route_name'] = $routeName;
            $insert['is_canonical'] = ($seoUrl['isCanonical'] ?? true) ? 1 : null;
            $insert['is_modified'] = ($seoUrl['isModified'] ?? false) ? 1 : 0;
            $insert['is_deleted'] = ($seoUrl['isDeleted'] ?? true) ? 1 : 0;

            $insert['created_at'] = $dateTime;

            $insertQuery->addInsert($table, $insert);
        }

        $inuseSeoUrls = $this->findInUseCanonicalSeoUrls($seoPathInfos, $languageId, $channelId, $tenantId);

        RetryableTransaction::retryable($this->connection, function () use ($obsoleted, $insertQuery, $foreignKeys, $updatedFks, $channelId, $tenantId): void {
            $this->obsoleteIds($obsoleted, $channelId, $tenantId);
            $insertQuery->execute();

            $deletedIds = array_diff($foreignKeys, $updatedFks);
            $notDeletedIds = array_unique(array_intersect($foreignKeys, $updatedFks));

            $this->markAsDeleted(true, $deletedIds, $channelId, $tenantId);
            $this->markAsDeleted(false, $notDeletedIds, $channelId, $tenantId);
        });

        // When a seoPathInfo is added that is already associated with a foreignKey, EX: Entity A,
        // the existing row is seamlessly taken over by the ON DUPLICATE KEY UPDATE part configured on the MultiInsertQueryQueue above.
        // Hence, we have to find the default seoUrls for Entity A and update it accordingly to set is_canonical and is_modified to true,
        // thereby preserving the canonical SEO URL for Entity A.
        $this->updateCanonicalSeoUrls($inuseSeoUrls, $languageId, $tenantId);

        $this->eventDispatcher->dispatch(new SeoUrlUpdateEvent($updates, $context));
    }

    /**
     * @param array{isModified: bool, seoPathInfo: string, channelId: string} $existing
     * @param array<string, mixed> $seoUrl the raw seo url as generated/handed in, so its keys are not statically typed
     */
    private function skipUpdate(array $existing, array $seoUrl, bool $overwrite = false): bool
    {
        // Write-protection guard: automatic template regeneration (overwrite=false)
        // must never replace a manually modified (isModified=1) SEO URL that still
        // has a non-empty path.
        if (!$overwrite && $existing['isModified'] && !($seoUrl['isModified'] ?? false) && trim((string) ($seoUrl['seoPathInfo'] ?? '')) !== '') {
            return true;
        }

        // A different path or channel is always a real change, so never skip.
        if ($seoUrl['seoPathInfo'] !== $existing['seoPathInfo']
            || $seoUrl['channelId'] !== $existing['channelId']) {
            return false;
        }

        // Path and channel are identical. Normally we skip to avoid creating a
        // duplicate row. For an explicit overwrite, however, we must still proceed when
        // only the isModified flag differs, so that an admin "reset to template" can drop
        // the write-protection flag even when the manual value already equals the template
        // output (contena/contena#4413). When the flag matches too, nothing changed -> skip.
        return !$overwrite
            || ($seoUrl['isModified'] ?? false) === $existing['isModified'];
    }

    /**
     * @param array<string> $foreignKeys
     *
     * @return array<string, mixed>
     */
    private function findCanonicalPaths(string $routeName, string $languageId, array $foreignKeys, ?string $tenantId): array
    {
        $fks = Uuid::fromHexToBytesList($foreignKeys);
        $languageId = Uuid::fromHexToBytes($languageId);

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(seo_url.id)) as id',
            'LOWER(HEX(seo_url.foreign_key)) foreignKey',
            'LOWER(HEX(seo_url.channel_id)) channelId',
            'seo_url.is_modified as isModified',
            'seo_url.seo_path_info seoPathInfo',
        );
        $query->from('seo_url', 'seo_url');

        $query->andWhere('seo_url.route_name = :routeName');
        $query->andWhere('seo_url.language_id = :language_id');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.foreign_key IN (:foreign_keys)');

        if ($tenantId !== null) {
            $query->andWhere('seo_url.tenant_id = :tenantId');
            $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        } else {
            $query->andWhere('seo_url.tenant_id IS NULL');
        }

        $query->setParameter('routeName', $routeName);
        $query->setParameter('language_id', $languageId);
        $query->setParameter('foreign_keys', $fks, ArrayParameterType::BINARY);

        $rows = $query->executeQuery()->fetchAllAssociative();

        $canonicals = [];
        foreach ($rows as $row) {
            $row['isModified'] = (bool) $row['isModified'];
            $foreignKey = (string) $row['foreignKey'];
            if (!isset($canonicals[$foreignKey])) {
                $canonicals[$foreignKey] = [$row['channelId'] => $row];

                continue;
            }
            $canonicals[$foreignKey][$row['channelId']] = $row;
        }

        return $canonicals;
    }

    /**
     * @param array<string> $seoPathInfos
     *
     * @return array<array<string, mixed>>
     */
    private function findInUseCanonicalSeoUrls(array $seoPathInfos, string $languageId, ?string $channelId, ?string $tenantId): array
    {
        if ($seoPathInfos === []) {
            return [];
        }

        $query = 'SELECT id, channel_id channelId, foreign_key foreignKey, route_name routeName
        FROM seo_url
        WHERE is_canonical = 1 AND language_id = :languageId AND seo_path_info IN (:seoPathInfos)';

        $params = ['seoPathInfos' => $seoPathInfos, 'languageId' => Uuid::fromHexToBytes($languageId)];
        $types = ['seoPathInfos' => ArrayParameterType::BINARY];

        if ($tenantId !== null) {
            $query .= ' AND tenant_id = :tenantId';
            $params['tenantId'] = Uuid::fromHexToBytes($tenantId);
        } else {
            $query .= ' AND tenant_id IS NULL';
        }

        if ($channelId !== null) {
            $query .= ' AND channel_id = :channelId';
            $params['channelId'] = Uuid::fromHexToBytes($channelId);
        }

        return $this->connection->fetchAllAssociative($query, $params, $types);
    }

    /**
     * Find the earliest valid SEO URL created. This means it is the default SEO URL and update the `is_canonical` and `is_modified` fields.
     *
     * @param array<array<string, mixed>> $seoUrls
     */
    private function updateCanonicalSeoUrls(array $seoUrls, string $languageId, ?string $tenantId): void
    {
        if ($seoUrls === []) {
            return;
        }

        $languageId = Uuid::fromHexToBytes($languageId);

        $ids = [];
        foreach ($seoUrls as $seoUrl) {
            $query = 'SELECT id
                      FROM seo_url
                      WHERE language_id = :languageId
                        AND foreign_key = :foreignKey
                        AND channel_id = :channelId
                        AND route_name = :routeName
                        AND is_canonical IS NULL AND is_deleted = 0';
            $parameters = [
                'languageId' => $languageId,
                'foreignKey' => $seoUrl['foreignKey'],
                'channelId' => $seoUrl['channelId'],
                'routeName' => (string) $seoUrl['routeName'],
            ];

            if ($tenantId !== null) {
                $query .= ' AND tenant_id = :tenantId';
                $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
            } else {
                $query .= ' AND tenant_id IS NULL';
            }

            $id = $this->connection->fetchOne($query . ' ORDER BY created_at ASC LIMIT 1', $parameters);

            if ($id !== false) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        RetryableQuery::retryable($this->connection, function () use ($ids): void {
            $this->connection->executeStatement(
                'UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        });
    }

    /**
     * @param list<string> $ids
     */
    private function obsoleteIds(array $ids, ?string $channelId, ?string $tenantId): void
    {
        if ($ids === []) {
            return;
        }

        $ids = Uuid::fromHexToBytesList($ids);

        $query = $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_canonical', 'NULL')
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::BINARY);

        if ($channelId) {
            $query->andWhere('channel_id = :channelId');
            $query->setParameter('channelId', Uuid::fromHexToBytes($channelId));
        }

        if ($tenantId !== null) {
            $query->andWhere('tenant_id = :tenantId');
            $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        } else {
            $query->andWhere('tenant_id IS NULL');
        }

        RetryableQuery::retryable($this->connection, static function () use ($query): void {
            $query->executeStatement();
        });
    }

    /**
     * @param array<string> $ids
     */
    private function markAsDeleted(bool $deleted, array $ids, ?string $channelId, ?string $tenantId): void
    {
        if ($ids === []) {
            return;
        }

        $ids = Uuid::fromHexToBytesList($ids);
        $query = $this->connection->createQueryBuilder()
            ->update('seo_url')
            ->set('is_deleted', $deleted ? '1' : '0')
            ->where('foreign_key IN (:fks)')
            // skip rows that already hold the target value to reduce write amplification
            // and lock contention between concurrent url generations (see NEXT-22174)
            ->andWhere('is_deleted != ' . ($deleted ? '1' : '0'))
            ->setParameter('fks', $ids, ArrayParameterType::BINARY);

        if ($channelId) {
            $query->andWhere('channel_id = :channelId');
            $query->setParameter('channelId', Uuid::fromHexToBytes($channelId));
        }

        if ($tenantId !== null) {
            $query->andWhere('tenant_id = :tenantId');
            $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        } else {
            $query->andWhere('tenant_id IS NULL');
        }

        $query->executeStatement();
    }
}
