<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationId;

use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\InstallationId\Fingerprint\AppUrl;
use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\FingerprintGenerator;
use Contena\Core\Framework\App\InstallationId\FingerprintMismatch;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use Contena\Core\Framework\App\InstallationId\InstallationIdChangedEvent;
use Contena\Core\Framework\App\InstallationId\InstallationIdDeletedEvent;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InstallationIdProvider::class)]
class InstallationIdProviderTest extends TestCase
{
    public function testGeneratesNewInstallationIdWhenNoOldInstallationIdPresent(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(3))
            ->method('get')
            ->with(InstallationIdProvider::INSTALLATION_ID_SYSTEM_CONFIG_KEY)
            ->willReturn(null);
        $systemConfigService->expects($this->exactly(2))
            ->method('set')
            ->with(InstallationIdProvider::INSTALLATION_ID_SYSTEM_CONFIG_KEY, static::callback(static function (array $config): bool {
                static::assertSame(2, $config['version'] ?? null);
                static::assertSame([], $config['fingerprints'] ?? null);

                return true;
            }));

        $provider = new InstallationIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            static::createStub(Connection::class),
            static::createStub(FingerprintGenerator::class),
        );

        $installationId = $provider->getInstallationId();

        static::assertCount(2, $eventDispatcher->getEvents());

        $installationIdChangedEvent = $eventDispatcher->getEvents()[0] ?? null;
        static::assertInstanceOf(InstallationIdChangedEvent::class, $installationIdChangedEvent);
        static::assertNull($installationIdChangedEvent->oldInstallationId);
        static::assertSame($installationId->id, $installationIdChangedEvent->newInstallationId->id);
    }

    public function testThrowsIfFingerprintsHaveChangedAndHasAppsRegisteredAtAppServers(): void
    {
        $installationId = InstallationId::create('1234567890');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with(InstallationIdProvider::INSTALLATION_ID_SYSTEM_CONFIG_KEY)
            ->willReturn((array) $installationId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $fingerprintGenerator = static::createStub(FingerprintGenerator::class);
        $fingerprintGenerator->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult(
                [],
                [
                    AppUrl::IDENTIFIER => new FingerprintMismatch(
                        AppUrl::IDENTIFIER,
                        'https://old.url',
                        'https://new.url',
                        100,
                    ),
                ],
                75,
            ));

        $provider = new InstallationIdProvider(
            $systemConfigService,
            new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
        );

        static::expectException(InstallationIdChangeSuggestedException::class);
        $provider->getInstallationId();
    }

    public function testUpdatesInstallationIdIfFingerprintsHaveChangedButHasNoAppsRegisteredAtAppServers(): void
    {
        $installationId = InstallationId::create('1234567890', [
            AppUrl::IDENTIFIER => 'https://old.url',
        ]);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->with(InstallationIdProvider::INSTALLATION_ID_SYSTEM_CONFIG_KEY)
            ->willReturnOnConsecutiveCalls((array) $installationId, (array) $installationId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult(
                [],
                [
                    AppUrl::IDENTIFIER => new FingerprintMismatch(
                        AppUrl::IDENTIFIER,
                        'https://old.url',
                        'https://new.url',
                        100,
                    ),
                ],
                75,
            ));
        $fingerprintGenerator->expects($this->once())
            ->method('takeFingerprints')
            ->willReturn([
                AppUrl::IDENTIFIER => 'https://new.url',
            ]);

        $provider = new InstallationIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
        );

        static::assertSame($installationId->id, $provider->getInstallationId()->id);
        // Fingerprints changed but the ID is reused, so this is not an installation identity change.
        static::assertCount(0, $eventDispatcher->getEvents());
    }

    public function testDeletesInstallationId(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('delete')
            ->with(InstallationIdProvider::INSTALLATION_ID_SYSTEM_CONFIG_KEY, null, false);

        $provider = new InstallationIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            static::createStub(Connection::class),
            static::createStub(FingerprintGenerator::class),
        );

        $provider->deleteInstallationId();

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(InstallationIdDeletedEvent::class, $eventDispatcher->getEvents()[0]);
    }
}
