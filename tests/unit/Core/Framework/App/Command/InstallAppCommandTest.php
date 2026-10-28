<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Command\AppPrinter;
use Contena\Core\Framework\App\Command\InstallAppCommand;
use Contena\Core\Framework\App\Exception\AppValidationException;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLoader;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\App\Validation\Error\MissingPermissionError;
use Contena\Core\Framework\App\Validation\ManifestValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallAppCommand::class)]
class InstallAppCommandTest extends TestCase
{
    private Stub&AppLoader $appLoader;

    private MockObject&AbstractAppLifecycle $appLifecycle;

    private MockObject&ManifestValidator $manifestValidator;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->appLoader = static::createStub(AppLoader::class);
        $this->appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $this->manifestValidator = $this->createMock(ManifestValidator::class);

        $this->commandTester = new CommandTester(new InstallAppCommand(
            $this->appLoader,
            $this->appLifecycle,
            static::createStub(AppPrinter::class),
            $this->manifestValidator
        ));
    }

    #[TestDox('A matching app is validated and installed with the activate flag')]
    public function testInstallsMatchingApp(): void
    {
        $manifest = $this->createManifest();
        $this->appLoader->method('load')->willReturn(['test' => $manifest]);
        $this->manifestValidator->expects($this->once())->method('validate')->with($manifest);
        $this->appLifecycle
            ->expects($this->once())
            ->method('install')
            ->willReturnCallback(function (Manifest $installed, AppInstallParameters $parameters, Context $context) use ($manifest): void {
                $this->assertSame($manifest, $installed);
                $this->assertTrue($parameters->activate);
                $this->assertTrue($parameters->acceptPermissions);
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'name' => ['test'],
            '--force' => true,
            '--activate' => true,
        ]));
        static::assertStringContainsString('App test has been successfully installed.', $this->commandTester->getDisplay());
    }

    #[TestDox('An unknown app name reports that no app was found')]
    public function testReportsUnknownApp(): void
    {
        $this->appLoader->method('load')->willReturn([]);
        $this->manifestValidator->expects($this->never())->method('validate');
        $this->appLifecycle->expects($this->never())->method('install');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'name' => ['unknown'],
        ]));
        static::assertStringContainsString('Could not find any app with this name', $this->commandTester->getDisplay());
    }

    #[TestDox('An already installed app is skipped with a notice')]
    public function testSkipsAlreadyInstalledApp(): void
    {
        $this->appLoader->method('load')->willReturn(['test' => $this->createManifest()]);
        $this->manifestValidator->expects($this->never())->method('validate');
        $this->appLifecycle
            ->expects($this->once())
            ->method('install')
            ->willThrowException(AppException::alreadyInstalled('test'));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'name' => ['test'],
            '--force' => true,
            '--no-validate' => true,
        ]));
        static::assertStringContainsString('App test is already installed', $this->commandTester->getDisplay());
    }

    #[TestDox('A validation error fails the installation of the app')]
    public function testFailsOnValidationError(): void
    {
        $this->appLoader->method('load')->willReturn(['test' => $this->createManifest()]);
        $this->manifestValidator
            ->expects($this->once())
            ->method('validate')
            ->willThrowException(new AppValidationException('test', new ErrorCollection([
                new MissingPermissionError(['product:read']),
            ])));
        $this->appLifecycle->expects($this->never())->method('install');

        static::assertSame(Command::FAILURE, $this->commandTester->execute([
            'name' => ['test'],
            '--force' => true,
        ]));
        static::assertStringContainsString('App installation of test failed due', $this->commandTester->getDisplay());
    }

    private function createManifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }
}
