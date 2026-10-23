<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeLifecycleHandler;

/**
 * @internal
 */
class ThemeLifecycleHandlerInheritanceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    private ThemeLifecycleHandler $handler;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->handler = static::getContainer()->get(ThemeLifecycleHandler::class);
    }

    /**
     * @return \Generator<string, array{string, int}>
     */
    public static function lifecycleAssignmentsProvider(): \Generator
    {
        yield 'deactivation names the grandchild copy and its sales channel' => ['deactivate', 1];
        yield 'uninstall names the grandchild copy and its sales channel' => ['uninstall', 1];
        yield 'deactivation lists both sales channels of the grandchild copy once' => ['deactivate', 2];
        yield 'uninstall lists both sales channels of the grandchild copy once' => ['uninstall', 2];
    }

    #[DataProvider('lifecycleAssignmentsProvider')]
    public function testAssignedGrandchildCopyPreventsRemovingBaseTheme(string $operation, int $channelCount): void
    {
        $base = $this->createTheme(name: 'Lifecycle Base', technicalName: 'LifecycleBase');
        $parent = $this->createTheme(name: 'Lifecycle Parent', technicalName: 'LifecycleParent', parentId: $base);
        $copy = $this->createTheme(name: 'Grandchild Copy', parentId: $parent);
        $channels = [];
        for ($index = 0; $index < $channelCount; ++$index) {
            $name = $index === 0 ? 'Frontend DE' : 'Frontend EN';
            $channels[$this->createChannel($copy, $name)] = $name;
        }

        $config = new FrontendPluginConfiguration('LifecycleBase');
        $config->setIsTheme(true);
        $this->expectExceptionObject(ThemeException::themeAssignmentException(
            'Lifecycle Base',
            [],
            ['Grandchild Copy' => array_keys($channels)],
            $channels,
        ));

        try {
            if ($operation === 'deactivate') {
                $this->handler->deactivateTheme($config, $this->context);
            } else {
                $this->handler->handleThemeUninstall($config, $this->context);
            }
        } finally {
            $themes = $this->themeRepository->search(new Criteria([$base, $parent, $copy]), $this->context)->getEntities();
            static::assertCount(3, $themes);
            foreach ($themes as $theme) {
                static::assertTrue($theme->isActive());
            }
        }
    }

    private function createTheme(string $name, ?string $technicalName = null, ?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        $this->themeRepository->create([[
            'id' => $id,
            'name' => $name,
            'technicalName' => $technicalName,
            'parentThemeId' => $parentId,
            'author' => 'Contena',
            'active' => true,
        ]], $this->context);

        return $id;
    }

    private function createChannel(string $themeId, string $name): string
    {
        $repository = static::getContainer()->get('channel.repository');
        $template = $repository->search(new Criteria([TestDefaults::CHANNEL]), $this->context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $template);
        $id = Uuid::randomHex();
        $repository->create([[
            'id' => $id,
            'name' => $name,
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
