<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Message\CompileThemeMessage;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeCompilerInterface;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeMergedConfigBuilder;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeRuntimeConfigStorage;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;

/**
 * @internal
 */
class ThemeServiceInheritanceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ThemeService $service;

    private ThemeRuntimeConfigService $runtimeService;

    private ThemeRuntimeConfigStorage $storage;

    private ThemeCompilerInterface&MockObject $compiler;

    private FrontendPluginConfigurationCollection $configurations;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    private Context $context;

    private StaticSystemConfigService $systemConfig;

    private MessageBus&MockObject $messageBus;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $connection = static::getContainer()->get(Connection::class);
        $this->configurations = clone static::getContainer()->get(FrontendPluginRegistry::class)->getConfigurations();
        $registry = static::createStub(FrontendPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($this->configurations);
        $builder = new ThemeMergedConfigBuilder($registry, $this->themeRepository);
        $resolver = static::createStub(ThemeFileResolver::class);
        $resolver->method('resolveScriptFiles')->willReturn(new FileCollection());
        $this->storage = new ThemeRuntimeConfigStorage($connection);
        $this->runtimeService = new ThemeRuntimeConfigService($resolver, $registry, $builder, $this->storage, new NativeClock());
        $this->compiler = $this->createMock(ThemeCompilerInterface::class);
        $this->systemConfig = new StaticSystemConfigService();
        $this->messageBus = $this->createMock(MessageBus::class);
        $this->service = new ThemeService(
            $registry,
            $this->themeRepository,
            static::getContainer()->get('theme_channel.repository'),
            $this->compiler,
            static::createStub(ScssPhpCompiler::class),
            new EventDispatcher(),
            new DatabaseConfigLoader($this->themeRepository, $registry, static::getContainer()->get('media.repository')),
            $connection,
            $this->systemConfig,
            $this->messageBus,
            static::createStub(NotificationService::class),
            $builder,
            $this->runtimeService,
        );
    }

    public function testDependencyMappingIncludesAllDescendantsAndChannelsOnce(): void
    {
        $base = $this->createTheme(name: 'InheritanceBase');
        $parent = $this->createTheme(name: 'InheritanceParent', parentId: $base);
        $child = $this->createTheme(name: 'InheritanceChild', parentId: $parent);
        $grandchild = $this->createTheme(name: 'InheritanceGrandchild', parentId: $child);
        $unrelated = $this->createTheme(name: 'UnrelatedTheme');

        $baseChannel = $this->createChannel($base);
        $parentChannel = $this->createChannel($parent);
        $childChannel = $this->createChannel($child);
        $secondChildChannel = $this->createChannel($child);
        $grandchildChannel = $this->createChannel($grandchild);
        $this->createChannel($unrelated);

        $mappings = [];
        foreach ($this->service->getThemeDependencyMapping($base) as $mapping) {
            $mappings[] = [$mapping->getThemeId(), $mapping->getChannelId()];
        }

        static::assertCount(5, $mappings);
        static::assertEqualsCanonicalizing([
            [$base, $baseChannel],
            [$parent, $parentChannel],
            [$child, $childChannel],
            [$child, $secondChildChannel],
            [$grandchild, $grandchildChannel],
        ], $mappings);
    }

    public function testSavingUnassignedBaseThemeUpdatesDescendantsAfterReset(): void
    {
        $base = $this->createTheme(
            name: 'InheritanceBase',
            values: ['sw-color-brand-primary' => '#ff0000'],
            inheritance: ['@Frontend'],
        );
        $parent = $this->createTheme(
            name: 'InheritanceParent',
            parentId: $base,
            inheritance: ['@Frontend', '@InheritanceBase'],
        );
        $child = $this->createTheme(
            name: 'InheritanceChild',
            parentId: $parent,
            values: ['sw-color-brand-secondary' => '#123456'],
            inheritance: ['@Frontend', '@InheritanceBase', '@InheritanceParent'],
        );
        $parentChannel = $this->createChannel($parent);
        $childChannel = $this->createChannel($child);
        $secondChildChannel = $this->createChannel($child);
        $this->runtimeService->refreshConfigValues($base, $this->context);

        // Administration saves with reset=true, then writes the final configuration.
        $this->service->resetTheme($base, $this->context);
        $resetRuntime = $this->storage->getById($child);
        static::assertNotNull($resetRuntime);
        static::assertSame('#0000ff', $resetRuntime->resolvedConfig['fields']['sw-color-brand-primary']['value']);

        $compiled = [];
        $this->compiler->expects($this->exactly(3))->method('compileTheme')
            ->willReturnCallback(static function (string $channelId, string $themeId, FrontendPluginConfiguration $config) use (&$compiled): void {
                $themeConfig = $config->getThemeConfig();
                static::assertNotNull($themeConfig);
                static::assertArrayHasKey('fields', $themeConfig);
                static::assertSame('#00ff00', $themeConfig['fields']['sw-color-brand-primary']['value']);
                $compiled[] = [$themeId, $channelId];
            });

        $this->service->updateTheme($base, ['sw-color-brand-primary' => ['value' => '#00ff00']], null, $this->context);

        static::assertEqualsCanonicalizing([
            [$parent, $parentChannel],
            [$child, $childChannel],
            [$child, $secondChildChannel],
        ], $compiled);
        foreach ([$base, $parent, $child] as $themeId) {
            $runtime = $this->storage->getById($themeId);
            static::assertNotNull($runtime);
            static::assertSame('#00ff00', $runtime->resolvedConfig['fields']['sw-color-brand-primary']['value']);
        }
        $childRuntime = $this->storage->getById($child);
        static::assertNotNull($childRuntime);
        static::assertSame('#123456', $childRuntime->resolvedConfig['fields']['sw-color-brand-secondary']['value']);
        $childEntity = $this->themeRepository->search(new Criteria([$child]), $this->context)->getEntities()->get($child);
        static::assertNotNull($childEntity);
        static::assertSame(['sw-color-brand-secondary' => ['value' => '#123456']], $childEntity->getConfigValues());
    }

    public function testSavingUnassignedThemeRefreshesItsOwnRuntime(): void
    {
        $base = $this->createTheme(name: 'InheritanceBase', values: ['sw-color-brand-primary' => '#ff0000']);
        $this->runtimeService->refreshConfigValues($base, $this->context);
        $this->service->resetTheme($base, $this->context);
        $this->compiler->expects($this->never())->method('compileTheme');

        $this->service->updateTheme($base, ['sw-color-brand-primary' => ['value' => '#00ff00']], null, $this->context);

        $runtime = $this->storage->getById($base);
        static::assertNotNull($runtime);
        static::assertSame('#00ff00', $runtime->resolvedConfig['fields']['sw-color-brand-primary']['value']);
    }

    public function testAsyncSaveRefreshesRuntimeBeforeDescendantCompilation(): void
    {
        $base = $this->createTheme(name: 'InheritanceBase', inheritance: ['@Frontend']);
        $parent = $this->createTheme(name: 'InheritanceParent', parentId: $base, inheritance: ['@Frontend', '@InheritanceBase']);
        $child = $this->createTheme(name: 'InheritanceChild', parentId: $parent, inheritance: ['@Frontend', '@InheritanceBase', '@InheritanceParent']);
        $parentChannel = $this->createChannel($parent);
        $childChannel = $this->createChannel($child);
        $this->runtimeService->refreshConfigValues($base, $this->context);
        $this->service->resetTheme($base, $this->context);
        $this->systemConfig->set(ThemeService::CONFIG_THEME_COMPILE_ASYNC, true);
        $this->compiler->expects($this->never())->method('compileTheme');
        $queued = [];
        $this->messageBus->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(static function (CompileThemeMessage $message) use (&$queued): Envelope {
                $queued[] = [$message->getThemeId(), $message->getChannelId()];

                return new Envelope($message);
            });

        $this->service->updateTheme($base, ['sw-color-brand-primary' => ['value' => '#00ff00']], null, $this->context);

        static::assertEqualsCanonicalizing([[$parent, $parentChannel], [$child, $childChannel]], $queued);
        foreach ([$base, $parent, $child] as $id) {
            $runtime = $this->storage->getById($id);
            static::assertNotNull($runtime);
            static::assertSame('#00ff00', $runtime->resolvedConfig['fields']['sw-color-brand-primary']['value']);
        }
    }

    /**
     * @param array<string, string> $values
     * @param list<string> $inheritance
     */
    private function createTheme(string $name, ?string $parentId = null, array $values = [], array $inheritance = []): string
    {
        $id = Uuid::randomHex();
        $config = ['fields' => [], 'configInheritance' => $inheritance];
        if ($parentId === null) {
            $config['fields'] = [
                'sw-color-brand-primary' => ['type' => 'color', 'value' => '#0000ff'],
                'sw-color-brand-secondary' => ['type' => 'color', 'value' => '#111111'],
            ];
        }
        $configuration = new FrontendPluginConfiguration($name);
        $configuration->setThemeConfig($config);
        $this->configurations->add($configuration);
        $this->themeRepository->create([[
            'id' => $id,
            'name' => $name,
            'technicalName' => $name,
            'author' => 'Contena',
            'active' => true,
            'parentThemeId' => $parentId,
            'baseConfig' => $config,
            'configValues' => array_map(static fn (string $value): array => ['value' => $value], $values),
        ]], $this->context);

        return $id;
    }

    private function createChannel(string $themeId): string
    {
        $repository = static::getContainer()->get('channel.repository');
        $template = $repository->search(new Criteria([TestDefaults::CHANNEL]), $this->context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $template);
        $id = Uuid::randomHex();
        $repository->create([[
            'id' => $id,
            'name' => 'Theme inheritance regression',
            'accessKey' => Uuid::randomHex(),
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethodId' => $template->getPaymentMethodId(),
            'shippingMethodId' => $template->getShippingMethodId(),
            'navigationCategoryId' => $template->getNavigationCategoryId(),
            'countryId' => $template->getCountryId(),
            'userGroupId' => $template->getUserGroupId(),
        ]], $this->context);
        static::getContainer()->get('theme_channel.repository')->upsert([[
            'themeId' => $themeId,
            'channelId' => $id,
        ]], $this->context);

        return $id;
    }
}
