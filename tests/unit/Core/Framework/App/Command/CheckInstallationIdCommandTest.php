<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Command;

use Contena\Core\Framework\App\Command\CheckInstallationIdCommand;
use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\FingerprintGenerator;
use Contena\Core\Framework\App\InstallationId\FingerprintMatch;
use Contena\Core\Framework\App\InstallationId\FingerprintMismatch;
use Contena\Core\System\SystemConfig\SystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(CheckInstallationIdCommand::class)]
class CheckInstallationIdCommandTest extends TestCase
{
    public function testDisplaysHintWhenNoInstallationIdExistsYet(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $fingerprintGenerator = static::createStub(FingerprintGenerator::class);

        $commandTester = new CommandTester(
            new CheckInstallationIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('No installation ID has been generated yet.', $commandTester->getDisplay());
    }

    public function testDisplaysInstallationId(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->willReturn([
                'id' => 'installation-id',
                'version' => 2,
                'fingerprints' => ['app_url' => 'https://foo.bar'],
            ]);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([], [], 75));

        $commandTester = new CommandTester(
            new CheckInstallationIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Installation ID: installation-id', $commandTester->getDisplay());
        static::assertStringContainsString('Version: 2', $commandTester->getDisplay());
    }

    public function testDisplaysFingerprintsAndSuggestionIfFingerprintsDoNotMatch(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(1))
            ->method('get')
            ->willReturn([
                'id' => 'installation-id-v2',
                'version' => 2,
                'fingerprints' => [
                    'fingerprint-1' => 'stored-stamp-1',
                    'fingerprint-2' => 'stored-stamp-2',
                    'fingerprint-3' => 'stored-stamp-3',
                ],
            ]);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([
                'fingerprint1' => new FingerprintMatch('fingerprint-1', 'stored-stamp-1', 25),
            ], [
                'fingerprint2' => new FingerprintMismatch('fingerprint-2', 'stored-stamp-2', 'expected-stamp-2', 50),
                'fingerprint3' => new FingerprintMismatch('fingerprint-3', 'stored-stamp-3', 'expected-stamp-3', 75),
            ], 75));

        $commandTester = new CommandTester(
            new CheckInstallationIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('Installation ID change suggested (Score: 125/75).', $commandTester->getDisplay());
    }

    public function testDisplaysFingerprintsAndSuggestionIfFingerprintsMatch(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(1))
            ->method('get')
            ->willReturn([
                'id' => 'installation-id-v2',
                'version' => 2,
                'fingerprints' => [
                    'fingerprint-1' => 'stored-stamp-1',
                    'fingerprint-2' => 'stored-stamp-2',
                    'fingerprint-3' => 'stored-stamp-3',
                ],
            ]);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([
                'fingerprint1' => new FingerprintMatch('fingerprint-1', 'stored-stamp-1', 25),
                'fingerprint2' => new FingerprintMatch('fingerprint-2', 'stored-stamp-2', 50),
                'fingerprint3' => new FingerprintMatch('fingerprint-3', 'stored-stamp-3', 75),
            ], [], 75));

        $commandTester = new CommandTester(
            new CheckInstallationIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Installation ID change not suggested.', $commandTester->getDisplay());
    }
}
