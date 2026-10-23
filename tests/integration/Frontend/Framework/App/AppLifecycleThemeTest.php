<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\App;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Event\AppActivatedEvent;
use Contena\Core\Framework\App\Event\AppDeactivatedEvent;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\Exception\ThemeAssignmentException;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class AppLifecycleThemeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private ThemeService $themeService;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepo;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepo;

    private AbstractAppLifecycle $appLifecycle;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $templateRepo;

    protected function setUp(): void
    {
        $this->themeService = static::getContainer()->get(ThemeService::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->appRepo = static::getContainer()->get('app.repository');
        $this->themeRepo = static::getContainer()->get('theme.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->templateRepo = static::getContainer()->get('app_template.repository');
        $this->appLifecycle = static::getContainer()->get(AppLifecycle::class);
    }

    public function testAppWithAThemeInUseCannotBeDeactivated(): void
    {
        $context = Context::createDefaultContext();
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/SwagTheme');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        $themeId = $this->themeRepo->searchIds($criteria, $context)->firstId();
        static::assertIsString($themeId);
        $channelId = $this->createChannel();

        $this->themeService->assignTheme($themeId, $channelId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
        static::assertIsString($appId);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ThemeAssignmentException::class);
        } else {
            $this->expectException(ThemeException::class);
        }
        $this->expectExceptionMessageMatches('/^Unable to deactivate or uninstall theme/');

        $this->appLifecycle->deactivate($appId, $context);
    }

    public function testAppWithAChildThemeInUseCannotBeDeactivated(): void
    {
        $context = Context::createDefaultContext();
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/SwagTheme');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        $themeId = $this->themeRepo->searchIds($criteria, $context)->firstId();

        $childId = Uuid::randomHex();
        $childTheme = [
            'id' => $childId,
            'name' => 'child',
            'parentThemeId' => $themeId,
            'author' => 'author',
            'active' => true,
        ];

        $this->themeRepo->upsert([[
            'id' => $themeId,
            'dependentThemes' => [$childTheme],
        ]], $context);

        $channelId = $this->createChannel();

        $this->themeService->assignTheme($childId, $channelId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
        static::assertIsString($appId);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ThemeAssignmentException::class);
        } else {
            $this->expectException(ThemeException::class);
        }
        $this->expectExceptionMessageMatches('/^Unable to deactivate or uninstall theme/');
        $this->appLifecycle->deactivate($appId, $context);
    }

    public function testAppWithAThemeCanBeDeactivated(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/SwagTheme');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
        static::assertIsString($appId);

        $receivedEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(AppDeactivatedEvent::class, static function (AppDeactivatedEvent $event) use (&$receivedEvent): void {
            $receivedEvent = $event;
        });

        $this->appLifecycle->deactivate($appId, $context);

        static::assertInstanceOf(AppDeactivatedEvent::class, $receivedEvent);
        static::assertSame($appId, $receivedEvent->getApp()->getId());
        static::assertSame($context, $receivedEvent->getContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        static::assertSame(0, $this->templateRepo->search($criteria, $context)->getTotal());
    }

    public function testAppWithAThemeCanBeActivated(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/SwagTheme', false);
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
        static::assertIsString($appId);

        $receivedEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(AppActivatedEvent::class, static function (AppActivatedEvent $event) use (&$receivedEvent): void {
            $receivedEvent = $event;
        });

        $this->appLifecycle->activate($appId, $context);

        static::assertInstanceOf(AppActivatedEvent::class, $receivedEvent);
        static::assertSame($appId, $receivedEvent->getApp()->getId());
        static::assertSame($context, $receivedEvent->getContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        // We expect 1 frontend twig template and svg image to be stored in the DB
        $expectedTemplates = 2;
        static::assertSame($expectedTemplates, $this->templateRepo->search($criteria, $context)->getTotal());
    }

    private function createChannel(): string
    {
        $channelRepository = static::getContainer()->get('channel.repository');

        $id = Uuid::randomHex();
        $payload = [[
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'active' => true,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ]];

        $channelRepository->create($payload, Context::createDefaultContext());

        return $id;
    }
}
