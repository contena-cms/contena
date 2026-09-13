<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog;

use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorageTest;
use Doctrine\DBAL\Connection;

/**
 * Keeps the consent log in the `cookie_consent_log` and `cookie_consent_config_snapshot`
 * tables of the system database.
 *
 * Snapshots are never deleted: they hold no personal data, there is one row per data
 * scope and configuration hash, and keeping them is what lets a decision be written
 * with a single insert instead of a transaction that guards against concurrent cleanup.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see DatabaseCookieConsentLogStorageTest
 *
 * @phpstan-type LogRow array{id: string, data_scope_id: string, consent_id: string, consent_action: string, group_decisions: string, accepted_cookies: string, config_hash: string, channel_id: string, language_id: string, created_at: string}
 */
final class DatabaseCookieConsentLogStorage extends AbstractCookieConsentLogStorage
{
    public const string NAME = 'database';

    private const int DELETE_BATCH_SIZE = 10000;

    private const int READ_BATCH_SIZE = 1000;

    private const string SELECT_LOG = 'SELECT `id`, LOWER(HEX(`data_scope_id`)) AS `data_scope_id`, `consent_id`, `consent_action`, `group_decisions`, `accepted_cookies`, `config_hash`,
        LOWER(HEX(`channel_id`)) AS `channel_id`, LOWER(HEX(`language_id`)) AS `language_id`, `created_at`
        FROM `cookie_consent_log`';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function log(CookieConsentRecord $record): void
    {
        $this->connection->insert('cookie_consent_log', [
            'id' => Uuid::randomBytes(),
            'data_scope_id' => Uuid::fromHexToBytes($record->dataScopeId),
            'consent_id' => $record->consentId,
            'consent_action' => $record->consentAction->value,
            'group_decisions' => json_encode(
                array_map(static fn (CookieConsentDecision $decision) => $decision->value, $record->groupDecisions),
                \JSON_THROW_ON_ERROR | \JSON_FORCE_OBJECT,
            ),
            'accepted_cookies' => json_encode($record->acceptedCookies, \JSON_THROW_ON_ERROR),
            'config_hash' => $record->configHash,
            'channel_id' => Uuid::fromHexToBytes($record->channelId),
            'language_id' => Uuid::fromHexToBytes($record->languageId),
            'created_at' => $record->createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO `cookie_consent_config_snapshot` (`id`, `data_scope_id`, `config_hash`, `cookie_groups`, `created_at`)
            VALUES (:id, :dataScopeId, :configHash, :cookieGroups, :createdAt)',
            [
                'id' => Uuid::randomBytes(),
                'dataScopeId' => Uuid::fromHexToBytes($snapshot->dataScopeId),
                'configHash' => $snapshot->configHash,
                'cookieGroups' => json_encode($snapshot->cookieGroups, \JSON_THROW_ON_ERROR),
                'createdAt' => $snapshot->createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    /**
     * Deletes in batches so a large table does not hold locks for too long.
     */
    public function cleanup(\DateTimeImmutable $before): void
    {
        do {
            // executeStatement() is typed int|string in DBAL 4, the strict comparison
            // below needs an int or the loop would stop after one batch
            $deleted = (int) $this->connection->executeStatement(
                'DELETE FROM `cookie_consent_log` WHERE `created_at` < :before LIMIT ' . self::DELETE_BATCH_SIZE,
                ['before' => $before->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            );
        } while ($deleted === self::DELETE_BATCH_SIZE);
    }

    /**
     * Pages by (created_at, id) instead of OFFSET, so an export of a large table does
     * not get slower with every batch.
     */
    public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $channelId = null): iterable
    {
        $parameters = [
            'from' => $from->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'to' => $to->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
        $conditions = ['`created_at` >= :from', '`created_at` < :to'];

        if ($channelId !== null) {
            $conditions[] = '`channel_id` = :channelId';
            $parameters['channelId'] = Uuid::fromHexToBytes($channelId);
        }

        $keysetCondition = '';
        do {
            /** @var list<LogRow> $rows */
            $rows = $this->connection->fetchAllAssociative(
                self::SELECT_LOG
                . ' WHERE ' . implode(' AND ', $conditions) . $keysetCondition
                . ' ORDER BY `created_at`, `id` LIMIT ' . self::READ_BATCH_SIZE,
                $parameters,
            );

            foreach ($rows as $row) {
                yield $this->hydrate($row);
            }

            $last = end($rows);
            if ($last !== false) {
                $keysetCondition = ' AND (`created_at` > :lastCreatedAt OR (`created_at` = :lastCreatedAt AND `id` > :lastId))';
                $parameters['lastCreatedAt'] = $last['created_at'];
                $parameters['lastId'] = $last['id'];
            }
        } while (\count($rows) === self::READ_BATCH_SIZE);
    }

    /**
     * @param LogRow $row
     */
    private function hydrate(array $row): CookieConsentRecord
    {
        /** @var array<string, string> $groupDecisions */
        $groupDecisions = json_decode($row['group_decisions'], true, 512, \JSON_THROW_ON_ERROR);
        /** @var list<string> $acceptedCookies */
        $acceptedCookies = json_decode($row['accepted_cookies'], true, 512, \JSON_THROW_ON_ERROR);

        return new CookieConsentRecord(
            dataScopeId: $row['data_scope_id'],
            consentId: $row['consent_id'],
            consentAction: CookieConsentAction::from($row['consent_action']),
            groupDecisions: array_map(CookieConsentDecision::from(...), $groupDecisions),
            acceptedCookies: $acceptedCookies,
            configHash: $row['config_hash'],
            channelId: $row['channel_id'],
            languageId: $row['language_id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }
}
