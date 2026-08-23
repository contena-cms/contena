<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Theme\Command\ThemeChangeCommand;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeService;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ThemeChangeCommand::class)]
class ThemeChangeCommandTest extends TestCase
{
    public function testItDeletesUnusedDirectoriesAfterChange(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())->method('deleteUnusedDirectories')->willReturn(2);

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Frontend', '--all' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItSkipsCleanupWhenNoCleanupOptionIsPassed(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->never())->method('deleteUnusedDirectories');

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Frontend', '--all' => true, '--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItCleansUpEvenWhenCompilationIsSkipped(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())->method('deleteUnusedDirectories')->willReturn(0);

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Frontend', '--all' => true, '--no-compile' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    private function createCommand(UnusedThemeDirectoryDeleter $unusedThemeDirectoryDeleter): ThemeChangeCommand
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setUniqueIdentifier($channel->getId());
        $channel->setName('Web');

        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $theme->setUniqueIdentifier($theme->getId());
        $theme->setTechnicalName('Frontend');

        /** @var StaticEntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = new StaticEntityRepository([new ChannelCollection([$channel])]);
        /** @var StaticEntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = new StaticEntityRepository([new ThemeCollection([$theme])]);

        $command = new ThemeChangeCommand(
            static::createStub(ThemeService::class),
            static::createStub(FrontendPluginRegistry::class),
            $channelRepository,
            $themeRepository,
            $unusedThemeDirectoryDeleter
        );

        new Application()->addCommand($command);

        return $command;
    }
}
