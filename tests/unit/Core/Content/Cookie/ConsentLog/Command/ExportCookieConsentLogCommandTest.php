<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ConsentLog\Command;

use Contena\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ConsentLog\Command\ExportCookieConsentLogCommand;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Contena\Core\Test\TestDefaults;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ExportCookieConsentLogCommand::class)]
class ExportCookieConsentLogCommandTest extends TestCase
{
    public function testItExportsTheRequestedRangeAsOneJsonArray(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->once())
            ->method('iterate')
            ->with(
                static::callback(static fn (\DateTimeImmutable $from) => $from->format('Y-m-d H:i:s') === '2026-01-01 00:00:00'),
                static::callback(static fn (\DateTimeImmutable $to) => $to->format('Y-m-d H:i:s') === '2026-07-01 00:00:00'),
                TestDefaults::CHANNEL,
            )
            ->willReturn($this->records());

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute([
            '--from' => '2026-01-01',
            '--to' => '2026-07-01',
            '--channel' => TestDefaults::CHANNEL,
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);

        $output = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($output);
        static::assertCount(2, $output);
        static::assertSame('visitor-a', $output[0]['consentId']);
        static::assertSame(['cookie.groupStatistical' => 'accepted'], $output[0]['groupDecisions']);
        static::assertSame('visitor-b', $output[1]['consentId']);
    }

    public function testAnEmptyRangeIsAnEmptyJsonArray(): void
    {
        $storage = static::createStub(AbstractCookieConsentLogStorage::class);
        $storage->method('iterate')->willReturn([]);

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $tester->execute([]);

        static::assertSame("[]\n", $tester->getDisplay());
    }

    public function testItExportsCsvWithAHeaderRow(): void
    {
        $storage = static::createStub(AbstractCookieConsentLogStorage::class);
        $storage->method('iterate')->willReturn($this->records());

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--format' => 'csv']);

        static::assertSame(Command::SUCCESS, $exitCode);

        $lines = explode("\n", trim($tester->getDisplay()));
        static::assertCount(3, $lines);
        static::assertSame(
            'dataScopeId,consentId,createdAt,consentAction,channelId,languageId,configHash,groupDecisions,acceptedCookies',
            $lines[0],
        );
        static::assertSame(
            'data-scope-id,visitor-a,2026-07-13T12:00:00.000+00:00,accept_all,channel-id,language-id,hash,"{""cookie.groupStatistical"":""accepted""}","[""lorem"",""ipsum""]"',
            $lines[1],
        );
        static::assertStringStartsWith('data-scope-id,visitor-b,', $lines[2]);
    }

    public function testItRejectsAnUnknownFormat(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->never())->method('iterate');

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--format' => 'xml']);

        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('Unknown format "xml"', $tester->getDisplay());
    }

    public function testItRejectsAnInvalidChannelId(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->never())->method('iterate');

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--channel' => 'not-a-uuid']);

        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('"not-a-uuid" is not a valid channel id', $tester->getDisplay());
    }

    public function testItRejectsAnUnparsableDate(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->never())->method('iterate');

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--from' => 'yesterday-ish']);

        static::assertSame(Command::INVALID, $exitCode);
    }

    /**
     * @return list<CookieConsentRecord>
     */
    private function records(): array
    {
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC'));

        return [
            new CookieConsentRecord(
                dataScopeId: 'data-scope-id',
                consentId: 'visitor-a',
                consentAction: CookieConsentAction::ACCEPT_ALL,
                groupDecisions: ['cookie.groupStatistical' => CookieConsentDecision::ACCEPTED],
                acceptedCookies: ['lorem', 'ipsum'],
                configHash: 'hash',
                channelId: 'channel-id',
                languageId: 'language-id',
                createdAt: $createdAt,
            ),
            new CookieConsentRecord(
                dataScopeId: 'data-scope-id',
                consentId: 'visitor-b',
                consentAction: CookieConsentAction::ACCEPT_REQUIRED,
                groupDecisions: ['cookie.groupStatistical' => CookieConsentDecision::REJECTED],
                acceptedCookies: [],
                configHash: 'hash',
                channelId: 'channel-id',
                languageId: 'language-id',
                createdAt: $createdAt->modify('+1 hour'),
            ),
        ];
    }
}
