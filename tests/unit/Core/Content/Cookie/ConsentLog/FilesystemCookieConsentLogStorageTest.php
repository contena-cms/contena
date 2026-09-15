<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ConsentLog;

use Contena\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Contena\Core\Content\Cookie\ConsentLog\FilesystemCookieConsentLogStorage;
use Contena\Core\Content\Cookie\CookieException;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FilesystemCookieConsentLogStorage::class)]
class FilesystemCookieConsentLogStorageTest extends TestCase
{
    private Filesystem $filesystem;

    private FilesystemCookieConsentLogStorage $storage;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->storage = new FilesystemCookieConsentLogStorage($this->filesystem);
    }

    public function testADecisionBecomesOneFileInItsHourDirectory(): void
    {
        // Stored in UTC, so the same decision always lands in the same directory
        $record = $this->record('visitor-a', new \DateTimeImmutable('2026-07-13 14:02:57.270', new \DateTimeZone('Europe/Berlin')));

        $this->storage->log($record);

        $files = $this->files('cookie-consent/2026/07/13/12');
        static::assertCount(1, $files);
        static::assertMatchesRegularExpression('#^cookie-consent/2026/07/13/12/20260713T120257270Z\.data-scope-id\.[0-9a-f]{8}\.visitor-a\.json$#', $files[0]);
        static::assertEquals([$record], [...$this->storage->iterate(new \DateTimeImmutable('2026-07-13'), new \DateTimeImmutable('2026-07-14'))]);
    }

    public function testAConsentIdThatIsNotFileSafeIsRejected(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentId('../etc/passwd'));

        $this->storage->log($this->record('../etc/passwd', new \DateTimeImmutable('2026-07-13 12:00:00')));
    }

    public function testASnapshotIsStoredOncePerHash(): void
    {
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC'));
        $this->storage->snapshot(new CookieConsentConfigSnapshot('data-scope-id', 'hash', [['technicalName' => 'cookie.groupRequired']], $createdAt));
        // A later call with the same hash keeps the original file
        $this->storage->snapshot(new CookieConsentConfigSnapshot('data-scope-id', 'hash', [], $createdAt->modify('+1 day')));

        static::assertSame(['cookie-consent/snapshots/data-scope-id/hash.json'], $this->files('cookie-consent/snapshots/data-scope-id'));
        static::assertSame(
            ['dataScopeId' => 'data-scope-id', 'configHash' => 'hash', 'cookieGroups' => [['technicalName' => 'cookie.groupRequired']], 'createdAt' => '2026-07-13T12:00:00.000+00:00'],
            json_decode($this->filesystem->read('cookie-consent/snapshots/data-scope-id/hash.json'), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testTheSameSnapshotHashIsIsolatedPerDataScope(): void
    {
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC'));

        $this->storage->snapshot(new CookieConsentConfigSnapshot('platform-scope', 'shared-hash', [], $createdAt));
        $this->storage->snapshot(new CookieConsentConfigSnapshot('tenant-scope', 'shared-hash', [], $createdAt));

        static::assertTrue($this->filesystem->fileExists('cookie-consent/snapshots/platform-scope/shared-hash.json'));
        static::assertTrue($this->filesystem->fileExists('cookie-consent/snapshots/tenant-scope/shared-hash.json'));
    }

    public function testCleanupDeletesExpiredHourDirectoriesAndEmptyParents(): void
    {
        $this->storage->log($this->record('old-year', new \DateTimeImmutable('2025-12-31 23:59:59')));
        $this->storage->log($this->record('old-hour', new \DateTimeImmutable('2026-03-14 10:59:59')));
        $this->storage->log($this->record('current-hour', new \DateTimeImmutable('2026-03-14 11:30:00')));
        $this->storage->log($this->record('future', new \DateTimeImmutable('2026-03-14 12:00:00')));
        $this->storage->snapshot(new CookieConsentConfigSnapshot('data-scope-id', 'hash', [], new \DateTimeImmutable('2025-01-01')));

        // Falls inside the 11:00 hour: that directory is kept until the whole hour has expired
        $this->storage->cleanup(new \DateTimeImmutable('2026-03-14 11:45:00', new \DateTimeZone('UTC')));

        static::assertFalse($this->filesystem->directoryExists('cookie-consent/2025'));
        static::assertFalse($this->filesystem->directoryExists('cookie-consent/2026/03/14/10'));
        static::assertTrue($this->filesystem->directoryExists('cookie-consent/2026/03/14/11'));
        static::assertTrue($this->filesystem->directoryExists('cookie-consent/2026/03/14/12'));
        static::assertTrue($this->filesystem->fileExists('cookie-consent/snapshots/data-scope-id/hash.json'));
        static::assertSame(['current-hour', 'future'], $this->consentIds($this->storage->iterate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2030-01-01'))));
    }

    public function testIterateFiltersByRangeAndChannelInChronologicalOrder(): void
    {
        $this->storage->log($this->record('before-range', new \DateTimeImmutable('2026-06-30 23:59:59.999')));
        $this->storage->log($this->record('second', new \DateTimeImmutable('2026-07-01 00:00:00.500')));
        $this->storage->log($this->record('first', new \DateTimeImmutable('2026-07-01 00:00:00.100')));
        $this->storage->log($this->record('other-channel', new \DateTimeImmutable('2026-07-02 00:00:00'), channelId: 'other-channel'));
        $this->storage->log($this->record('at-upper-bound', new \DateTimeImmutable('2026-08-01 00:00:00')));

        $from = new \DateTimeImmutable('2026-07-01', new \DateTimeZone('UTC'));
        $to = new \DateTimeImmutable('2026-08-01', new \DateTimeZone('UTC'));

        static::assertSame(['first', 'second', 'other-channel'], $this->consentIds($this->storage->iterate($from, $to)));
        static::assertSame(['first', 'second'], $this->consentIds($this->storage->iterate($from, $to, 'channel-id')));
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
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $files = [];
        foreach ($this->filesystem->listContents($directory) as $item) {
            if ($item->isFile()) {
                $files[] = $item->path();
            }
        }
        sort($files);

        return $files;
    }

    private function record(string $consentId, \DateTimeImmutable $createdAt, CookieConsentAction $action = CookieConsentAction::ACCEPT_ALL, string $channelId = 'channel-id'): CookieConsentRecord
    {
        return new CookieConsentRecord(
            dataScopeId: 'data-scope-id',
            consentId: $consentId,
            consentAction: $action,
            groupDecisions: ['cookie.groupRequired' => CookieConsentDecision::ACCEPTED, 'cookie.groupStatistical' => CookieConsentDecision::PARTIAL],
            acceptedCookies: ['lorem'],
            configHash: 'hash',
            channelId: $channelId,
            languageId: 'language-id',
            createdAt: $createdAt->getTimezone()->getName() === 'UTC' ? $createdAt : $createdAt->setTimezone(new \DateTimeZone('UTC')),
        );
    }
}
