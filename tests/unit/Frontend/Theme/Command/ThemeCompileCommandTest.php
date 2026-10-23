<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Theme\Command\ThemeCompileCommand;
use Contena\Frontend\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Contena\Frontend\Theme\ThemeService;
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
        $channelId = 'sales-channel-id';
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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

        $commandTester->execute(['--active-only' => $activeOnly]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItSetsSyncThemeCompileContextState(): void
    {
        $channelId = 'sales-channel-id';
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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

        $commandTester->execute(['--sync' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesSkipChannelFlagCorrectly(): void
    {
        $channelIdSkip1 = 'sales-channel-id1';
        $channelIdSkip2 = 'sales-channel-id2';
        $channelIdIncluded1 = 'sales-channel-id3';
        $channelIdIncluded2 = 'sales-channel-id4';
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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

        $commandTester->execute(['--skip' => [$channelIdSkip1, $channelIdSkip2]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesOnlyChannelFlagCorrectly(): void
    {
        $channelIdSkip1 = 'sales-channel-id1';
        $channelIdSkip2 = 'sales-channel-id2';
        $channelIdIncluded1 = 'sales-channel-id3';
        $channelIdIncluded2 = 'sales-channel-id4';
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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

        $commandTester->execute(['--only' => [$channelIdIncluded1, $channelIdIncluded2]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesSkipThemeFlagCorrectly(): void
    {
        $channelIdSkip1 = 'sales-channel-id1';
        $channelIdSkip2 = 'sales-channel-id2';
        $channelIdIncluded1 = 'sales-channel-id3';
        $channelIdIncluded2 = 'sales-channel-id4';
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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

        $commandTester->execute(['--skip-themes' => [$themeIdSkip]]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItPassesOnlyThemeFlagCorrectly(): void
    {
        $channelIdSkip1 = 'sales-channel-id1';
        $channelIdSkip2 = 'sales-channel-id2';
        $channelIdIncluded1 = 'sales-channel-id3';
        $channelIdIncluded2 = 'sales-channel-id4';
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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

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

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

        $themeId = Uuid::randomHex();
        $commandTester->execute([
            '--only-themes' => [$themeId],
            '--skip-themes' => [$themeId],
        ]);
        static::assertSame(1, $commandTester->getStatusCode());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed together with the `--no-cleanup` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testItStillAcceptsTheDeprecatedNoCleanupOption(): void
    {
        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())->method('compileTheme');

        $themeProvider = static::createMock(AbstractAvailableThemeProvider::class);
        $themeProvider->expects($this->once())
            ->method('load')
            ->willReturn(['sales-channel-id' => 'theme-id']);

        $commandTester = new CommandTester(new ThemeCompileCommand($themeService, $themeProvider, new NativeClock()));

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
