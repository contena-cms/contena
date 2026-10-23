<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Theme\Command\ThemeChangeCommand;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ThemeChangeCommand::class)]
class ThemeChangeCommandTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed together with the `--no-cleanup` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testItStillAcceptsTheDeprecatedNoCleanupOption(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setUniqueIdentifier($channel->getId());
        $channel->setName('Frontend');

        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $theme->setUniqueIdentifier($theme->getId());
        $theme->setTechnicalName('Frontend');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('assignTheme')
            ->with($theme->getId(), $channel->getId(), static::anything(), false);

        $command = new ThemeChangeCommand(
            $themeService,
            static::createStub(FrontendPluginRegistry::class),
            new StaticEntityRepository([new ChannelCollection([$channel])]),
            new StaticEntityRepository([new ThemeCollection([$theme])])
        );

        // register the command on an application so the "question" helper set is available
        (new Application())->addCommand($command);

        $commandTester = new CommandTester($command);

        $commandTester->execute(['theme-name' => 'Frontend', '--all' => true, '--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }
}
