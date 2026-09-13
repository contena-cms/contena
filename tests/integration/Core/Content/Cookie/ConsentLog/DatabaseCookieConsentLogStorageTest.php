<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cookie\ConsentLog;

use Contena\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Contena\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieEntryCollection;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DatabaseCookieConsentLogStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const string OTHER_CHANNEL = '0189f0c6d9a273f8a4e3c4b1d5f2e6a7';

    private Connection $connection;

    private DatabaseCookieConsentLogStorage $storage;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->storage = new DatabaseCookieConsentLogStorage($this->connection);
    }

    public function testARecordSurvivesTheRoundTrip(): void
    {
        $record = $this->record('visitor-a', new \DateTimeImmutable('2026-07-13 12:00:00.123'), [
            'consentAction' => CookieConsentAction::ACCEPT_SELECTED,
            'groupDecisions' => [
                'cookie.groupRequired' => CookieConsentDecision::ACCEPTED,
                'cookie.groupStatistical' => CookieConsentDecision::PARTIAL,
            ],
            'acceptedCookies' => ['lorem'],
        ]);

        $this->storage->log($record);

        static::assertEquals([$record], [...$this->storage->iterate(new \DateTimeImmutable('2026-07-13'), new \DateTimeImmutable('2026-07-14'))]);
    }

    public function testASnapshotIsStoredOncePerHash(): void
    {
        $group = new CookieGroup('cookie.groupStatistical');
        $group->name = 'Statistics';
        $group->setEntries(new CookieEntryCollection([new CookieEntry('lorem')]));

        $this->storage->snapshot(new CookieConsentConfigSnapshot(Defaults::PLATFORM_DATA_SCOPE, 'hash', [$group], new \DateTimeImmutable('2026-07-13 12:00:00')));
        // A later call with the same hash keeps the original row
        $this->storage->snapshot(new CookieConsentConfigSnapshot(Defaults::PLATFORM_DATA_SCOPE, 'hash', [], new \DateTimeImmutable('2026-07-14 12:00:00')));

        $rows = $this->connection->fetchAllAssociative('SELECT `config_hash`, `cookie_groups`, `created_at` FROM `cookie_consent_config_snapshot`');
        static::assertCount(1, $rows);
        static::assertSame('hash', $rows[0]['config_hash']);
        static::assertSame('2026-07-13 12:00:00.000', $rows[0]['created_at']);

        $cookieGroups = json_decode((string) $rows[0]['cookie_groups'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($cookieGroups);
        static::assertCount(1, $cookieGroups);
        static::assertSame('cookie.groupStatistical', $cookieGroups[0]['technicalName']);
        static::assertSame('Statistics', $cookieGroups[0]['name']);
        static::assertSame('lorem', $cookieGroups[0]['entries'][0]['cookie']);
    }

    public function testTheSameSnapshotHashIsIsolatedPerDataScope(): void
    {
        $tenantId = Uuid::randomHex();
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00');
        $this->connection->insert('data_scope', [
            'id' => Uuid::fromHexToBytes($tenantId),
            'type' => 'tenant',
            'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->storage->snapshot(new CookieConsentConfigSnapshot(Defaults::PLATFORM_DATA_SCOPE, 'shared-hash', [], $createdAt));
        $this->storage->snapshot(new CookieConsentConfigSnapshot($tenantId, 'shared-hash', [], $createdAt));

        $rows = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`data_scope_id`)) FROM `cookie_consent_config_snapshot` WHERE `config_hash` = :hash ORDER BY `data_scope_id`', ['hash' => 'shared-hash']);
        static::assertEqualsCanonicalizing([Defaults::PLATFORM_DATA_SCOPE, $tenantId], $rows);
    }

    public function testCleanupDeletesOldDecisionsButKeepsSnapshots(): void
    {
        $this->storage->snapshot(new CookieConsentConfigSnapshot(Defaults::PLATFORM_DATA_SCOPE, 'old-hash', [], new \DateTimeImmutable('2025-01-01 12:00:00')));
        $this->storage->log($this->record('expired', new \DateTimeImmutable('2026-03-14 11:59:59.999')));
        $this->storage->log($this->record('kept', new \DateTimeImmutable('2026-03-14 12:00:00.000')));

        $this->storage->cleanup(new \DateTimeImmutable('2026-03-14 12:00:00'));

        static::assertSame(['kept'], $this->connection->fetchFirstColumn('SELECT `consent_id` FROM `cookie_consent_log`'));
        static::assertSame(['old-hash'], $this->connection->fetchFirstColumn('SELECT `config_hash` FROM `cookie_consent_config_snapshot`'));
    }

    public function testIterateFiltersByRangeAndChannel(): void
    {
        $this->storage->log($this->record('before-range', new \DateTimeImmutable('2026-06-30 23:59:59.999')));
        $this->storage->log($this->record('in-range', new \DateTimeImmutable('2026-07-01 00:00:00')));
        $this->storage->log($this->record('other-channel', new \DateTimeImmutable('2026-07-02 00:00:00'), ['channelId' => self::OTHER_CHANNEL]));
        $this->storage->log($this->record('at-upper-bound', new \DateTimeImmutable('2026-08-01 00:00:00')));

        $all = $this->storage->iterate(new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-08-01'));
        static::assertSame(['in-range', 'other-channel'], $this->consentIds($all));

        $oneChannel = $this->storage->iterate(new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-08-01'), TestDefaults::CHANNEL);
        static::assertSame(['in-range'], $this->consentIds($oneChannel));
    }

    public function testIteratePagesThroughMoreThanOneBatchWithoutSkippingOrRepeating(): void
    {
        // Same timestamp for every row, so the id tie-breaker of the keyset pagination is exercised
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $rows = [];
        for ($i = 0; $i < 1001; ++$i) {
            $rows[] = \sprintf(
                '(%s, %s, \'visitor-%04d\', \'accept_all\', \'{}\', \'[]\', \'hash\', %s, %s, \'%s\')',
                $this->connection->quote(Uuid::randomBytes()),
                $this->connection->quote(Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE)),
                $i,
                $this->connection->quote(Uuid::fromHexToBytes(TestDefaults::CHANNEL)),
                $this->connection->quote(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)),
                $createdAt,
            );
        }
        $this->connection->executeStatement(
            'INSERT INTO `cookie_consent_log` (`id`, `data_scope_id`, `consent_id`, `consent_action`, `group_decisions`, `accepted_cookies`, `config_hash`, `channel_id`, `language_id`, `created_at`) VALUES '
            . implode(', ', $rows),
        );

        $consentIds = $this->consentIds($this->storage->iterate(new \DateTimeImmutable('2026-07-13'), new \DateTimeImmutable('2026-07-14')));

        static::assertCount(1001, $consentIds);
        static::assertCount(1001, array_unique($consentIds));
    }

    /**
     * @param iterable<CookieConsentRecord> $records
     *
     * @return list<string>
     */
    private function consentIds(iterable $records): array
    {
        $consentIds = [];
        foreach ($records as $record) {
            $consentIds[] = $record->consentId;
        }

        return $consentIds;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function record(string $consentId, \DateTimeImmutable $createdAt, array $overrides = []): CookieConsentRecord
    {
        return new CookieConsentRecord(
            dataScopeId: Defaults::PLATFORM_DATA_SCOPE,
            consentId: $consentId,
            consentAction: $overrides['consentAction'] ?? CookieConsentAction::ACCEPT_ALL,
            groupDecisions: $overrides['groupDecisions'] ?? ['cookie.groupRequired' => CookieConsentDecision::ACCEPTED],
            acceptedCookies: $overrides['acceptedCookies'] ?? [],
            configHash: 'hash',
            channelId: $overrides['channelId'] ?? TestDefaults::CHANNEL,
            languageId: Defaults::LANGUAGE_SYSTEM,
            createdAt: $createdAt,
        );
    }
}
