<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Contena\Frontend\Theme\Command\ThemeDumpCommand;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigDumper;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ThemeDumpCommandTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private string $parentThemeId;

    private string $childThemeId;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $dumpedConfig = null;

    public function testExecuteShouldResolveThemeInheritanceChainAndConsiderThemeIdArgument(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->expects($this->once())->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem());

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->createMock(StaticFileConfigDumper::class),
            $themeFilesystemResolver
        );

        $commandTester = new CommandTester($themeDumpCommand);

        $commandTester->execute([
            'theme-id' => $this->childThemeId,
            'domain-url' => 'http://localhost/1/' . $this->childThemeId,
        ]);

        static::assertSame(['any' => 'expectedConfig'], $themeFileResolverMock->themeConfig->getThemeConfig());
    }

    #[DataProvider('getArguments')]
    public function testExecuteShouldSuccess(?string $themeId = null, ?string $domainUrl = null): void
    {
        $this->setUpExampleThemes($themeId);

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem());

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->createMock(StaticFileConfigDumper::class),
            $themeFilesystemResolverMock
        );

        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));
        $commandTester = new CommandTester($themeDumpCommand);

        $userInput = [];

        if (!$themeId) {
            $userInput[] = 'Parent theme';
        }

        if (!$domainUrl) {
            $userInput[] = 'http://localhost/1/' . $this->parentThemeId;
        }

        $commandTester->setInputs($userInput);
        $commandTester->execute([
            'theme-id' => $themeId,
            'domain-url' => $domainUrl,
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testExecuteShouldSuccessWithoutInteraction(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem());

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->createStaticFileConfigDumperMock(),
            $themeFilesystemResolverMock
        );
        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $commandTester->execute([], ['interactive' => false]);

        // Without interaction the domain is resolved from the theme's frontend sales channel instead of being
        // dumped as an empty string.
        $commandTester->assertCommandIsSuccessful();
        static::assertIsArray($this->dumpedConfig);
        static::assertNotSame('', $this->dumpedConfig['domainUrl']);
    }

    public function testExecuteResolvesSingleDomainWithoutInteraction(): void
    {
        ['themeId' => $themeId, 'domainUrl' => $domainUrl] = $this->setUpSingleDomainTheme();

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem());

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->createStaticFileConfigDumperMock(),
            $themeFilesystemResolverMock
        );
        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $commandTester->execute(['theme-id' => $themeId], ['interactive' => false]);

        $commandTester->assertCommandIsSuccessful();
        static::assertIsArray($this->dumpedConfig);
        static::assertSame($domainUrl, $this->dumpedConfig['domainUrl']);
    }

    public function testInteractiveModeDisplaysThemeAssignmentInfos(): void
    {
        $this->setUpExampleThemes();

        $themeFileResolverMock = new ThemeFileResolverMock();
        $themeFilesystemResolverMock = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolverMock->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem());

        $themeDumpCommand = new ThemeDumpCommand(
            $this->getPluginRegistryMock(),
            $themeFileResolverMock,
            static::getContainer()->get('theme.repository'),
            $this->createMock(StaticFileConfigDumper::class),
            $themeFilesystemResolverMock
        );
        $themeDumpCommand->setHelperSet(new HelperSet([new QuestionHelper()]));

        $commandTester = new CommandTester($themeDumpCommand);
        $commandTester->setInputs(['Parent theme', 'http://localhost/1/' . $this->parentThemeId]);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('Theme assignment:', $output);
        static::assertStringContainsString('Parent theme || Assigned to:', $output);
        static::assertStringContainsString('Child theme || Assigned to:', $output);

        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @return list<array{themeId: string|null, domainUrl: string|null}>
     */
    public static function getArguments(): array
    {
        $themeId = Uuid::randomHex();

        return [
            [
                'themeId' => $themeId,
                'domainUrl' => null,
            ],
            [
                'themeId' => $themeId,
                'domainUrl' => 'http://localhost/1/' . $themeId,
            ],
            [
                'themeId' => null,
                'domainUrl' => 'http://localhost/2/' . $themeId,
            ],
            [
                'themeId' => null,
                'domainUrl' => null,
            ],
        ];
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

    private function setUpExampleThemes(?string $parentThemeId = null): void
    {
        $themeRepository = static::getContainer()->get('theme.repository');
        $themeChannelRepository = static::getContainer()->get('theme_channel.repository');
        $context = Context::createDefaultContext();

        $parentThemeId = $parentThemeId ?? Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->childThemeId = $childId;
        $this->parentThemeId = $parentThemeId;

        $themes = [
            $parentThemeId => Uuid::randomHex(),
            $childId => Uuid::randomHex(),
        ];

        $themeRepository->create(
            [
                [
                    'id' => $parentThemeId,
                    'name' => 'Parent theme',
                    'technicalName' => 'parentTheme',
                    'author' => 'test',
                    'active' => true,
                ],
                [
                    'id' => $childId,
                    'parentThemeId' => $parentThemeId,
                    'name' => 'Child theme',
                    'author' => 'test',
                    'active' => true,
                ],
            ],
            $context
        );

        foreach ($themes as $themeId => $channelId) {
            $this->createChannel([
                'id' => $channelId,
                'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/1/' . $themeId,
                    ],
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/2/' . $themeId,
                    ],
                ],
            ]);

            $themeChannelRepository->create([['themeId' => $themeId, 'channelId' => $channelId]], $context);
        }
    }

    /**
     * @return array{themeId: string, domainUrl: string}
     */
    private function setUpSingleDomainTheme(): array
    {
        $themeRepository = static::getContainer()->get('theme.repository');
        $themeChannelRepository = static::getContainer()->get('theme_channel.repository');
        $context = Context::createDefaultContext();

        $themeId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $domainUrl = 'http://localhost/single/' . $themeId;

        $themeRepository->create(
            [
                [
                    'id' => $themeId,
                    'name' => 'Single domain theme',
                    // Has to match a technical name known to the plugin registry mock.
                    'technicalName' => 'parentTheme',
                    'author' => 'test',
                    'active' => true,
                ],
            ],
            $context
        );

        $this->createChannel([
            'id' => $channelId,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $domainUrl,
                ],
            ],
        ]);

        $themeChannelRepository->create([['themeId' => $themeId, 'channelId' => $channelId]], $context);

        return ['themeId' => $themeId, 'domainUrl' => $domainUrl];
    }

    private function createStaticFileConfigDumperMock(): StaticFileConfigDumper&MockObject
    {
        $staticFileConfigDumper = $this->createMock(StaticFileConfigDumper::class);
        $staticFileConfigDumper->method('dumpConfigInVar')->willReturnCallback(
            /**
             * @param array<string, mixed> $dump
             */
            function (string $filePath, array $dump): void {
                $this->dumpedConfig = $dump;
            }
        );

        return $staticFileConfigDumper;
    }
}

/**
 * @internal
 */
class ThemeFileResolverMock extends ThemeFileResolver
{
    public FrontendPluginConfiguration $themeConfig;

    public function __construct()
    {
    }

    public function resolveFiles(
        FrontendPluginConfiguration $themeConfig,
        FrontendPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): array {
        $this->themeConfig = $themeConfig;

        return [];
    }
}
