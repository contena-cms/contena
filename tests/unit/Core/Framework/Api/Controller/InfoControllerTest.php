<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\Controller\InfoController;
use Contena\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Contena\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Contena\Core\Framework\App\ShopId\ShopId;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\App\Url\AppUrlVerifier;
use Contena\Core\Framework\App\Url\VerificationState;
use Contena\Core\Framework\App\Url\VerificationStatus;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Increment\IncrementGatewayRegistry;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Contena\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Contena\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Migration\MigrationInfo;
use Contena\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InfoController::class)]
class InfoControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private ShopIdProvider&MockObject $shopIdProvider;

    private StatsService&Stub $statsService;

    private MigrationInfo&Stub $migrationInfo;

    private AppUrlVerifier&Stub $appUrlVerifier;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->statsService = static::createStub(StatsService::class);
        $this->migrationInfo = static::createStub(MigrationInfo::class);
        $this->appUrlVerifier = static::createStub(AppUrlVerifier::class);
        $this->eventDispatcher = new EventDispatcher();

        $shopId = ShopId::v2('shop-id');
        $this->shopIdProvider->method('getShopId')->willReturn($shopId);
    }

    public function testConfig(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->setEnvVars([
            'APP_URL' => 'https://app.url',
        ]);

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('version', $data);
        static::assertSame('6.6.9999999-dev', $data['version']);
        static::assertArrayHasKey('versionRevision', $data);
        static::assertSame('PHPUnit', $data['versionRevision']);
        static::assertArrayHasKey('adminWorker', $data);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('shop-id', $data['shopId']);
        static::assertArrayHasKey('appUrl', $data);
        static::assertSame('https://app.url', $data['appUrl']);

        $workerConfig = $data['adminWorker'];
        static::assertArrayHasKey('enableAdminWorker', $workerConfig);
        static::assertTrue($workerConfig['enableAdminWorker']);
        if (!Feature::isActive('v6.8.0.0')) {
            static::assertArrayHasKey('enableQueueStatsWorker', $workerConfig);
            static::assertTrue($workerConfig['enableQueueStatsWorker']);
        }
        static::assertArrayHasKey('enableNotificationWorker', $workerConfig);
        static::assertTrue($workerConfig['enableNotificationWorker']);
        static::assertArrayHasKey('transports', $workerConfig);
        static::assertIsArray($workerConfig['transports']);
        static::assertCount(1, $workerConfig['transports']);
        static::assertSame('slow', $workerConfig['transports'][0]);

        static::assertArrayHasKey('settings', $data);
        $settings = $data['settings'];
        static::assertIsArray($settings);
        static::assertArrayHasKey('enableUrlFeature', $settings);
        static::assertTrue($settings['enableUrlFeature']);
        static::assertArrayHasKey('appUrlReachable', $settings);
        static::assertFalse($settings['appUrlReachable']);
        static::assertArrayHasKey('appsRequireAppUrl', $settings);
        static::assertFalse($settings['appsRequireAppUrl']);
        static::assertArrayHasKey('firstMigrationDate', $settings);
        static::assertTrue(
            $settings['firstMigrationDate'] === null
            || \is_string($settings['firstMigrationDate'])
        );
        static::assertArrayHasKey('private_allowed_extensions', $settings);
        static::assertSame(['pdf', 'epub'], $settings['private_allowed_extensions']);
        static::assertArrayHasKey('private_allowed_mime_types_by_extension', $settings);
        static::assertIsArray($settings['private_allowed_mime_types_by_extension']);
        static::assertContains('application/pdf', $settings['private_allowed_mime_types_by_extension']['pdf']);
        static::assertSame(['application/epub+zip'], $settings['private_allowed_mime_types_by_extension']['epub']);
        static::assertArrayHasKey('enableHtmlSanitizer', $settings);
        static::assertTrue($settings['enableHtmlSanitizer']);
        static::assertArrayHasKey('minSearchTermLength', $settings);
        static::assertSame(2, $settings['minSearchTermLength']);
        static::assertArrayHasKey('hideUpdateModule', $settings);
        static::assertFalse($settings['hideUpdateModule']);

        static::assertArrayHasKey('inAppPurchases', $data);
        $inAppPurchases = $data['inAppPurchases'];
        static::assertIsArray($inAppPurchases);
        static::assertCount(1, $inAppPurchases);
        static::assertArrayHasKey('SwagApp', $inAppPurchases);
        static::assertSame(['SwagApp_premium'], $inAppPurchases['SwagApp']);
    }

    public function testReturnsCurrentShopIdIfShopIdFingerprintsHaveChanged(): void
    {
        $this->shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('current-shop-id'), new FingerprintComparisonResult([], [], 75)));

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('current-shop-id', $data['shopId']);
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testConfigHidesWebhookTransportWhenWebhookReworkIsInactive(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $content = $this->createController(['webhook', 'async', 'low_priority'])
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['async', 'low_priority'], $data['adminWorker']['transports']);
    }

    public function testConfigKeepsWebhookTransportWhenWebhookReworkIsActive(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $content = $this->createController(['webhook', 'async', 'low_priority'])
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['webhook', 'async', 'low_priority'], $data['adminWorker']['transports']);
    }

    public function testConfigExtension(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->eventDispatcher->addListener(AdminInfoConfigEvent::class, static function (AdminInfoConfigEvent $event): void {
            $event->addConfig('foo', 'bar');
        });

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('foo', $data);
        static::assertSame('bar', $data['foo']);
    }

    public function testMessageStatsPreservesFloatingPointPrecision(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(
                true,
                new MessageStatsEntity(1, new \DateTime(), 1.00, new MessageTypeStatsCollection())
            )
        );
        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('stats', $data);
        static::assertArrayHasKey('averageTimeInQueue', $data['stats']);

        // Check that the floating point precision is preserved for zero-padded decimal values
        static::assertSame(1.00, $data['stats']['averageTimeInQueue']);
    }

    public function testConfigReturnsNullFirstMigrationDateWhenMigrationInfoReturnsNull(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->migrationInfo->method('getFirstMigrationDate')->willReturn(null);

        $response = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'));
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('settings', $data);
        static::assertArrayHasKey('firstMigrationDate', $data['settings']);
        static::assertNull($data['settings']['firstMigrationDate']);
    }

    public function testConfigReturnsNullFirstMigrationDateWhenMigrationInfoReturnsNullAgain(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->migrationInfo->method('getFirstMigrationDate')->willReturn(null);

        $response = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'));
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('settings', $data);
        static::assertArrayHasKey('firstMigrationDate', $data['settings']);
        static::assertNull($data['settings']['firstMigrationDate']);
    }

    public function testConfigReturnsFirstMigrationDateFromMigrationInfo(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->migrationInfo->method('getFirstMigrationDate')->willReturn('2020-01-01T00:00:00.123+00:00');

        $response = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'));
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('settings', $data);
        static::assertArrayHasKey('firstMigrationDate', $data['settings']);
        static::assertSame('2020-01-01T00:00:00.123+00:00', $data['settings']['firstMigrationDate']);
    }

    public function testConfigReportsTheStatusCodeOfAFailedAppUrlCheck(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->appUrlVerifier->method('verifyForAdministration')->willReturn(false);
        $this->appUrlVerifier->method('getCurrentState')->willReturn(
            new VerificationState('https://app.url', VerificationStatus::HARD_FAIL, 1, new \DateTimeImmutable(), 'blocked', 403),
        );

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertFalse($data['settings']['appUrlReachable']);
        static::assertSame(403, $data['settings']['appUrlStatusCode']);
    }

    public function testConfigReportsNoStatusCodeWhenTheAppUrlCheckPassed(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->appUrlVerifier->method('verifyForAdministration')->willReturn(true);
        $this->appUrlVerifier->method('getCurrentState')->willReturn(
            new VerificationState('https://app.url', VerificationStatus::PASS, 1, new \DateTimeImmutable(), info: null, statusCode: 204),
        );

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertTrue($data['settings']['appUrlReachable']);
        static::assertNull($data['settings']['appUrlStatusCode']);
    }

    public function testConfigReturnsHideUpdateModuleWhenEnabled(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $response = $this->createController(hideUpdateModule: true)->config(Context::createDefaultContext(), Request::create('http://localhost'));
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('settings', $data);
        static::assertArrayHasKey('hideUpdateModule', $data['settings']);
        static::assertTrue($data['settings']['hideUpdateModule']);
    }

    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresMessageQueueStatsReadPrivilege(string $routeName): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $route = (new AttributeRouteControllerLoader())->load(InfoController::class)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, InfoController::class));
        static::assertSame(['message_queue_stats:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'queue stats' => ['api.info.queue'];
        yield 'message stats' => ['api.info.message-stats'];
    }

    public function testConfigDispatchesMediaFileExtensionWhitelistEventOnlyOnce(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $dispatchCount = 0;
        $this->eventDispatcher->addListener(
            MediaFileExtensionWhitelistEvent::class,
            function () use (&$dispatchCount): void {
                ++$dispatchCount;
            }
        );

        $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'));

        static::assertSame(
            1,
            $dispatchCount,
            'MediaFileExtensionWhitelistEvent must be dispatched exactly once per /api/_info/config request'
        );
    }

    /**
     * @param list<string> $adminWorkerTransports
     */
    private function createController(array $adminWorkerTransports = ['slow'], bool $hideUpdateModule = false): InfoController
    {
        $parameterBag = new ParameterBag([
            'contena.html_sanitizer.enabled' => true,
            'contena.filesystem.allowed_extensions' => [],
            'contena.filesystem.private_allowed_extensions' => ['pdf', 'epub'],
            'contena.admin_worker.transports' => $adminWorkerTransports,
            'contena.admin_worker.enable_notification_worker' => true,
            'contena.admin_worker.enable_queue_stats_worker' => true,
            'contena.admin_worker.enable_admin_worker' => true,
            'kernel.contena_version' => '6.6.9999999-dev',
            'kernel.contena_version_revision' => 'PHPUnit',
            'contena.media.enable_url_upload_feature' => true,
            'contena.staging.administration.show_banner' => false,
            'contena.deployment.runtime_extension_management' => true,
            'contena.auto_update.hide_module' => $hideUpdateModule,
        ]);

        return new InfoController(
            static::createStub(DefinitionService::class),
            $parameterBag,
            static::createStub(BusinessEventCollector::class),
            static::createStub(IncrementGatewayRegistry::class),
            $this->migrationInfo,
            $this->appUrlVerifier,
            static::createStub(ActiveAppsLoader::class),
            static::createStub(FlowActionCollector::class),
            new StaticSystemConfigService(),
            static::createStub(ApiRouteInfoResolver::class),
            StaticInAppPurchaseFactory::createWithFeatures(['SwagApp' => ['SwagApp_premium']]),
            $this->shopIdProvider,
            $this->statsService,
            $this->eventDispatcher,
            null,
            new MediaFileExtensionListProvider($this->eventDispatcher, [], ['pdf', 'epub']),
        );
    }
}
