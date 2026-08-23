<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Installer\Installer;
use Contena\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(AssetInstallCommand::class)]
class AssetInstallCommandTest extends TestCase
{
    public function testHtaccessCopy(): void
    {
        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('contena', true);
        $fs->mkdir($tmpDir . '/public');
        $fs->dumpFile($tmpDir . '/public/.htaccess.dist', 'FOO');

        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($tmpDir);

        $command = new AssetInstallCommand(
            $kernel,
            static::createStub(AssetService::class)
        );

        $runner = new CommandTester($command);
        $status = $runner->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertFileExists($tmpDir . '/public/.htaccess');
        static::assertFileEquals($tmpDir . '/public/.htaccess.dist', $tmpDir . '/public/.htaccess');

        $fs->remove($tmpDir);
    }

    public function testForceOptionIsForwardedToService(): void
    {
        $exampleBundle = $this->getBundle();
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([$exampleBundle]);

        $service = $this->createMock(AssetService::class);
        $invokedCount = $this->exactly(2);
        $service->expects($invokedCount)
            ->method('copyAssets')
            ->willReturnCallback(static function ($bundle, $force) use ($invokedCount, $exampleBundle): void {
                if ($invokedCount->numberOfInvocations() === 1) {
                    static::assertSame($exampleBundle, $bundle);
                    static::assertTrue($force);
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    static::assertInstanceOf(Installer::class, $bundle);
                    static::assertTrue($force);
                }
            });

        $command = new AssetInstallCommand(
            $kernel,
            $service
        );

        $runner = new CommandTester($command);
        $runner->execute(['--force' => true]);
    }

    private function getBundle(): ExampleBundle
    {
        return new ExampleBundle(true, __DIR__ . '/_fixtures/ExampleBundle');
    }
}
