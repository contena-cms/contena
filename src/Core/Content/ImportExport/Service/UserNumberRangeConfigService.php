<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Service;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserDefinition;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
final class UserNumberRangeConfigService implements ResetInterface
{
    private const GLOBAL_CACHE_KEY = 'global';

    /**
     * @var array<string, array{id: string, pattern: string}|null>
     */
    private array $cache = [];

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getPatternConfigId(?string $channelId): ?string
    {
        return $this->getPatternConfig($channelId)['id'] ?? null;
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    /**
     * @return array{id: string, pattern:string}|null
     */
    public function getPatternConfig(?string $channelId): ?array
    {
        $cacheKey = $channelId ?? self::GLOBAL_CACHE_KEY;
        if (\array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('number_range.id', 'number_range.pattern')
            ->from('number_range')
            ->innerJoin(
                'number_range',
                'number_range_type',
                'number_range_type',
                'number_range_type.id = number_range.type_id',
            )
            ->where('number_range_type.technical_name = :typeName')
            ->setParameter('typeName', UserDefinition::ENTITY_NAME)
            ->orderBy('number_range.global', 'ASC')
            ->addOrderBy('number_range_type.global', 'ASC');

        if ($channelId === null) {
            $queryBuilder->andWhere('(number_range_type.global = 1 OR number_range.global = 1)');
        } else {
            $queryBuilder
                ->leftJoin(
                    'number_range',
                    'number_range_channel',
                    'number_range_channel',
                    'number_range.id = number_range_channel.number_range_id',
                )
                ->andWhere('(number_range_channel.channel_id = :channelId OR number_range_type.global = 1 OR number_range.global = 1)')
                ->setParameter('channelId', Uuid::fromHexToBytes($channelId));
        }

        /** @var array{id: string, pattern: string}|false $configuration */
        $configuration = $queryBuilder->executeQuery()->fetchAssociative();

        if ($configuration === false || !isset($configuration['id'], $configuration['pattern'])) {
            return $this->cache[$cacheKey] = null;
        }

        $configuration['id'] = Uuid::fromBytesToHex($configuration['id']);

        return $this->cache[$cacheKey] = $configuration;
    }
}
