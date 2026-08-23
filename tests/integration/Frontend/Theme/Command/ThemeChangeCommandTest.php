<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Frontend\Theme\Command\ThemeChangeCommand;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeService;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ThemeChangeCommandTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    private Stub&FrontendPluginRegistry $pluginRegistry;

    private MockObject&ThemeService $themeService;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->pluginRegistry = $this->getPluginRegistryMock();
        $this->themeService = $this->createMock(ThemeService::class);

        $themeChangeCommand = new ThemeChangeCommand(
            $this->themeService,
            $this->pluginRegistry,
            $this->channelRepository,
            $this->themeRepository,
            static::createStub(UnusedThemeDirectoryDeleter::class)
        );

        $this->commandTester = new CommandTester($themeChangeCommand);
        $application = new Application();
        $application->addCommand($themeChangeCommand);
    }

    public function testThemeChangeCommandAllChannels(): void
    {
        $context = Context::createDefaultContext();

        $channels = $this->getChannelData();
        $themes = $this->getThemeData();

        foreach ($channels as $channel) {
            $this->createChannel($channel);
        }

        $this->themeRepository->create($themes, $context);

        $channels = $this->channelRepository->search(
            new Criteria()->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_WEB)),
            Context::createDefaultContext()
        )->getEntities();

        $this->themeService->expects($this->exactly(\count($channels)))
            ->method('assignTheme');

        $this->commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--all' => true,
        ]);
    }

    public function testThemeChangeCommandWithOneChannel(): void
    {
        $context = Context::createDefaultContext();

        $channel = $this->getChannelData()[0];
        $themes = $this->getThemeData();

        $this->createChannel($channel);

        $this->themeRepository->create($themes, $context);

        // without --sync the command defers the switch until the background compilation finished
        $expectedContext = Context::createCLIContext();
        $expectedContext->addState(ThemeService::STATE_DEFER_ASSIGNMENT);

        $this->themeService->expects($this->exactly(1))
            ->method('assignTheme')
            ->with($themes[0]['id'], $channel['id'], $expectedContext);

        $this->commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--channel' => $channel['id'],
        ]);
    }

    public function testThemeChangeCommandWithNotExistingChannelAndTheme(): void
    {
        $this->commandTester->execute(['theme-name' => 'not existing theme', '--channel' => 'not existing channel'], ['interactive' => true]);

        static::assertStringContainsString('[ERROR] Could not find channel with ID not existing channel', $this->commandTester->getDisplay());
    }

    public function testThemeChangeCommandWithNoChannel(): void
    {
        $this->commandTester->execute(['--all' => true, '--channel' => 'foo'], ['interactive' => true]);

        static::assertStringContainsString('[ERROR] You can use either --channel or --all, not both at the same time.', $this->commandTester->getDisplay());
    }

    public function testThemeChangeCommandWithOneChannelWithoutCompiling(): void
    {
        $context = Context::createCLIContext();

        $channel = $this->getChannelData()[0];
        $themes = $this->getThemeData();

        $this->createChannel($channel);

        $this->themeRepository->create($themes, $context);

        $this->themeService->expects($this->exactly(1))
            ->method('assignTheme')
            ->with($themes[0]['id'], $channel['id'], $context, true);

        $this->commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--channel' => $channel['id'],
            '--no-compile' => true,
        ]);
    }

    public function testThemeChangeCommandSync(): void
    {
        $context = Context::createCLIContext();
        $context->addState(ThemeService::STATE_NO_QUEUE);

        $channel = $this->getChannelData()[0];
        $themes = $this->getThemeData();

        $this->createChannel($channel);

        $this->themeRepository->create($themes, $context);

        $this->themeService->expects($this->exactly(1))
            ->method('assignTheme')
            ->with($themes[0]['id'], $channel['id'], $context, false);

        $this->commandTester->execute([
            'theme-name' => $themes[0]['technicalName'],
            '--channel' => $channel['id'],
            '--sync' => true,
        ]);
    }

    private function getPluginRegistryMock(): Stub&FrontendPluginRegistry
    {
        $storePluginConfiguration1 = new FrontendPluginConfiguration('parentTheme');
        $storePluginConfiguration1->setThemeConfig([
            'any' => 'expectedConfig',
        ]);

        $storePluginConfiguration2 = new FrontendPluginConfiguration('childTheme');
        $storePluginConfiguration2->setThemeConfig([
            'any' => 'unexpectedConfig',
        ]);

        $mock = static::createStub(FrontendPluginRegistry::class);

        $mock->method('getConfigurations')
            ->willReturn(
                new FrontendPluginConfigurationCollection([$storePluginConfiguration1, $storePluginConfiguration2])
            );

        return $mock;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getChannelData(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/channel1',
                    ],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/channel2',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getThemeData(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'name' => 'Theme1',
                'technicalName' => 'theme_1',
                'author' => 'test',
                'active' => true,
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'Theme2',
                'technicalName' => 'theme_2',
                'author' => 'test',
                'active' => true,
            ],
        ];
    }
}
