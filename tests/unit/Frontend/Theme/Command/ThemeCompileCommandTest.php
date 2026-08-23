<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Command\ThemeCompileCommand;
use Contena\Frontend\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Contena\Frontend\Theme\ThemeService;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ThemeCompileCommand::class)]
class ThemeCompileCommandTest extends TestCase
{
    #[DataProvider('getOptionsValue')]
    public function testItNegatesKeepAssetsOptionWhenPassed(bool $keepAssetsOption): void
    {
        $channelId = 'channel-id';
        $themeId = 'theme-id';

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('compileTheme')
            ->with($channelId, $themeId, static::anything(), null, !$keepAssetsOption);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([$channelId => $themeId]);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--keep-assets' => $keepAssetsOption]);
        $commandTester->assertCommandIsSuccessful();
    }

    #[DataProvider('getOptionsValue')]
    public function testItPassesActiveOnlyFlagCorrectly(bool $activeOnly): void
    {
        $themeService = static::createStub(ThemeService::class);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), $activeOnly)
            ->willReturn([]);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--active-only' => $activeOnly]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItSetsSyncThemeCompileContextState(): void
    {
        $channelId = 'channel-id';
        $themeId = 'theme-id';

        $context = Context::createDefaultContext();
        $context->addState(ThemeService::STATE_NO_QUEUE);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('compileTheme')
            ->with($channelId, $themeId, $context);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([$channelId => $themeId]);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--sync' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesSkipChannelFlagCorrectly(): void
    {
        $channelIdSkip1 = 'channel-id1';
        $channelIdSkip2 = 'channel-id2';
        $channelIdIncluded1 = 'channel-id3';
        $channelIdIncluded2 = 'channel-id4';
        $themeId = 'theme-id';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $channelIdSkip1 => $themeId,
                $channelIdSkip2 => $themeId,
                $channelIdIncluded1 => $themeId,
                $channelIdIncluded2 => $themeId,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                static function (
                    string $actualChannelId,
                    string $actualThemeId
                ) use (
                    $themeId,
                    $channelIdIncluded1,
                    $channelIdIncluded2
                ): void {
                    static::assertSame($themeId, $actualThemeId);
                    static::assertContains(
                        $actualChannelId,
                        [$channelIdIncluded1, $channelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--skip' => [$channelIdSkip1, $channelIdSkip2]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesOnlyChannelFlagCorrectly(): void
    {
        $channelIdSkip1 = 'channel-id1';
        $channelIdSkip2 = 'channel-id2';
        $channelIdIncluded1 = 'channel-id3';
        $channelIdIncluded2 = 'channel-id4';
        $themeId = 'theme-id';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $channelIdSkip1 => $themeId,
                $channelIdSkip2 => $themeId,
                $channelIdIncluded1 => $themeId,
                $channelIdIncluded2 => $themeId,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                static function (
                    string $actualChannelId,
                    string $actualThemeId
                ) use (
                    $themeId,
                    $channelIdIncluded1,
                    $channelIdIncluded2
                ): void {
                    static::assertSame($themeId, $actualThemeId);
                    static::assertContains(
                        $actualChannelId,
                        [$channelIdIncluded1, $channelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--only' => [$channelIdIncluded1, $channelIdIncluded2]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesSkipThemeFlagCorrectly(): void
    {
        $channelIdSkip1 = 'channel-id1';
        $channelIdSkip2 = 'channel-id2';
        $channelIdIncluded1 = 'channel-id3';
        $channelIdIncluded2 = 'channel-id4';
        $themeIdSkip = 'theme-id-skip';
        $themeIdIncluded = 'theme-id-included';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $channelIdSkip1 => $themeIdSkip,
                $channelIdSkip2 => $themeIdSkip,
                $channelIdIncluded1 => $themeIdIncluded,
                $channelIdIncluded2 => $themeIdIncluded,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                static function (
                    string $actualChannelId,
                    string $actualThemeId
                ) use (
                    $themeIdIncluded,
                    $channelIdIncluded1,
                    $channelIdIncluded2
                ): void {
                    static::assertSame($themeIdIncluded, $actualThemeId);
                    static::assertContains(
                        $actualChannelId,
                        [$channelIdIncluded1, $channelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--skip-themes' => [$themeIdSkip]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesOnlyThemeFlagCorrectly(): void
    {
        $channelIdSkip1 = 'channel-id1';
        $channelIdSkip2 = 'channel-id2';
        $channelIdIncluded1 = 'channel-id3';
        $channelIdIncluded2 = 'channel-id4';
        $themeIdSkip = 'theme-id-skip';
        $themeIdIncluded = 'theme-id-included';

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->with(static::anything(), false)
            ->willReturn([
                $channelIdSkip1 => $themeIdSkip,
                $channelIdSkip2 => $themeIdSkip,
                $channelIdIncluded1 => $themeIdIncluded,
                $channelIdIncluded2 => $themeIdIncluded,
            ]);

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->exactly(2))
            ->method('compileTheme')
            ->willReturnCallback(
                static function (
                    string $actualChannelId,
                    string $actualThemeId
                ) use (
                    $themeIdIncluded,
                    $channelIdIncluded1,
                    $channelIdIncluded2
                ): void {
                    static::assertSame($themeIdIncluded, $actualThemeId);
                    static::assertContains(
                        $actualChannelId,
                        [$channelIdIncluded1, $channelIdIncluded2]
                    );
                }
            );

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $commandTester->execute(['--only-themes' => [$themeIdIncluded]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItFailsWithContradictingChannelArgs(): void
    {
        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->never())
            ->method('load');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->never())
            ->method('compileTheme');

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $channelId = Uuid::randomHex();
        $commandTester->execute([
            '--only' => [$channelId],
            '--skip' => [$channelId],
        ]);
        static::assertSame(1, $commandTester->getStatusCode());
    }

    public function testItFailsWithContradictingThemeArgs(): void
    {
        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->never())
            ->method('load');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->never())
            ->method('compileTheme');

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), static::createStub(UnusedThemeDirectoryDeleter::class)));

        $themeId = Uuid::randomHex();
        $commandTester->execute([
            '--only-themes' => [$themeId],
            '--skip-themes' => [$themeId],
        ]);
        static::assertSame(1, $commandTester->getStatusCode());
    }

    public function testItDeletesUnusedThemeFilesAfterCompilation(): void
    {
        $themeService = static::createStub(ThemeService::class);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->willReturn(['channel-id' => 'theme-id']);

        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())
            ->method('deleteUnusedDirectories')
            ->willReturn(3);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), $unusedThemeDirectoryDeleter));

        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItSkipsCleanupWhenNoCleanupOptionIsPassed(): void
    {
        $themeService = static::createStub(ThemeService::class);

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->willReturn(['channel-id' => 'theme-id']);

        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->never())
            ->method('deleteUnusedDirectories');

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock(), $unusedThemeDirectoryDeleter));

        $commandTester->execute(['--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function getOptionsValue(): iterable
    {
        yield [true];
        yield [false];
    }
}
