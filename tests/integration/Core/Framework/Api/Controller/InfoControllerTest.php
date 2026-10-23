<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Contena\Core\Framework\App\Event\CustomAppEvent;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\A11yRenderedDocumentAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Event\UserGroupAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\LogAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
class InfoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    use AppSystemTestBehaviour;

    use EnvTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetConfig(): void
    {
        $this->setEnvVars([
            'APP_URL' => 'https://test-app.url',
        ]);

        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();

        $expected = [
            'version' => '6.7.9999999.9999999-dev',
            'shopId' => $shopId->id,
            'appUrl' => 'https://test-app.url',
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => true,
                'enableNotificationWorker' => true,
                'transports' => Feature::isActive('WEBHOOKS_REWORK')
                    ? ['webhook', 'async', 'low_priority']
                    : ['async', 'low_priority'],
                'enableQueueStatsWorker' => true,
            ],
            'bundles' => [],
            'settings' => [
                'enableUrlFeature' => true,
                'presignedUploadSupported' => false,
                'appUrlReachable' => true,
                'appsRequireAppUrl' => false,
                'firstMigrationDate' => null,
                'private_allowed_extensions' => [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'avif',
                    'gif',
                    'svg',
                    'bmp',
                    'tiff',
                    'tif',
                    'eps',
                    'webm',
                    'mkv',
                    'flv',
                    'ogv',
                    'ogg',
                    'mov',
                    'mp4',
                    'avi',
                    'wmv',
                    'pdf',
                    'aac',
                    'mp3',
                    'wav',
                    'flac',
                    'oga',
                    'wma',
                    'txt',
                    'doc',
                    'docx',
                    'ico',
                    'glb',
                    'zip',
                    'rar',
                    'csv',
                    'xls',
                    'xlsx',
                    'html',
                    'xml',
                    'vtt',
                    'srt',
                    'sub',
                    'ass',
                    'ssa',
                    'step',
                    'stp',
                ],
                'enableHtmlSanitizer' => true,
                'enableStagingMode' => false,
                'disableExtensionManagement' => false,
                'hideUpdateModule' => false,
                'minSearchTermLength' => 2,
            ],
            'inAppPurchases' => [],
        ];

        if (Feature::isActive('v6.8.0.0')) {
            unset($expected['adminWorker']['enableQueueStatsWorker']);
        }

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        static::assertArrayHasKey('private_allowed_mime_types_by_extension', $decodedResponse['settings']);
        static::assertIsArray($decodedResponse['settings']['private_allowed_mime_types_by_extension']);
        static::assertContains('application/pdf', $decodedResponse['settings']['private_allowed_mime_types_by_extension']['pdf']);

        // reset environment-based mismatch
        $decodedResponse['bundles'] = [];
        $decodedResponse['versionRevision'] = $expected['versionRevision'];
        $expected['settings']['firstMigrationDate'] = $decodedResponse['settings']['firstMigrationDate'];
        unset($decodedResponse['settings']['private_allowed_mime_types_by_extension']);

        static::assertSame($expected, $decodedResponse);
    }

    public function testGetConfigIncludesMimeTypesForEventAddedPrivateExtensions(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(MediaFileExtensionWhitelistEvent::class, static function (MediaFileExtensionWhitelistEvent $event): void {
            $extensions = $event->getWhitelist();
            $extensions[] = 'epub';

            $event->setWhitelist($extensions);
        });

        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/config');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        static::assertContains('epub', $decodedResponse['settings']['private_allowed_extensions']);
        static::assertSame(
            ['application/epub+zip'],
            $decodedResponse['settings']['private_allowed_mime_types_by_extension']['epub']
        );
    }

    public function testGetConfigWithPermissions(): void
    {
        $ids = new IdsCollection();
        $appRepository = static::getContainer()->get('app.repository');
        $appRepository->create([
            [
                'name' => 'PHPUnit',
                'path' => '/foo/bar',
                'active' => true,
                'configurable' => false,
                'version' => '1.0.0',
                'label' => 'PHPUnit',
                'integration' => [
                    'id' => $ids->create('integration'),
                    'label' => 'foo',
                    'accessKey' => '123',
                    'secretAccessKey' => '456',
                ],
                'aclRole' => [
                    'name' => 'PHPUnitRole',
                    'privileges' => [
                        'admin_user:create',
                        'admin_user:read',
                        'admin_user:update',
                        'admin_user:delete',
                        'admin_user_change_me',
                    ],
                ],
                'baseAppUrl' => 'https://example.com',
            ],
        ], Context::createDefaultContext());

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $bundle = [
            'active' => true,
            'integrationId' => $ids->get('integration'),
            'type' => 'app',
            'sourceType' => 'local',
            'baseUrl' => 'https://example.com',
            'permissions' => [
                'create' => ['admin_user'],
                'read' => ['admin_user'],
                'update' => ['admin_user'],
                'delete' => ['admin_user'],
                'additional' => ['admin_user_change_me'],
            ],
            'version' => '1.0.0',
            'name' => 'PHPUnit',
        ];

        $expected = [
            'version' => Kernel::CONTENA_FALLBACK_VERSION,
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => true,
                'transports' => [],
            ],
            'bundles' => $bundle,
            'settings' => [
                'enableUrlFeature' => true,
                'enableHtmlSanitizer' => true,
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        foreach (array_keys($expected) as $key) {
            static::assertArrayHasKey($key, $decodedResponse);
        }

        $bundles = $decodedResponse['bundles'];
        static::assertIsArray($bundles);
        static::assertArrayHasKey('PHPUnit', $bundles);
        static::assertIsArray($bundles['PHPUnit']);
        static::assertSame($bundle, $bundles['PHPUnit']);
    }

    public function testGetConfigWithServiceSourceType(): void
    {
        $ids = new IdsCollection();
        $appRepository = static::getContainer()->get('app.repository');
        $appRepository->create([
            [
                'name' => 'PHPUnitService',
                'path' => '/foo/bar',
                'active' => true,
                'configurable' => false,
                'version' => '1.0.0',
                'label' => 'PHPUnitService',
                'sourceType' => 'service',
                // Service apps are self-managed; this excludes them from the automatic script
                // refresh (ScriptLifecycleHandler::refresh) which would otherwise try to resolve
                // the service filesystem and fail without a full source config.
                'selfManaged' => true,
                'integration' => [
                    'id' => $ids->create('integration'),
                    'label' => 'foo',
                    'accessKey' => '123',
                    'secretAccessKey' => '456',
                ],
                'aclRole' => [
                    'name' => 'PHPUnitServiceRole',
                    'privileges' => [
                        'admin_user:read',
                    ],
                ],
                'baseAppUrl' => 'https://example.com',
            ],
        ], Context::createDefaultContext());

        $bundle = [
            'active' => true,
            'integrationId' => $ids->get('integration'),
            'type' => 'app',
            'sourceType' => 'service',
            'baseUrl' => 'https://example.com',
            'permissions' => [
                'read' => ['admin_user'],
            ],
            'version' => '1.0.0',
            'name' => 'PHPUnitService',
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $bundles = $decodedResponse['bundles'];
        static::assertIsArray($bundles);
        static::assertArrayHasKey('PHPUnitService', $bundles);
        static::assertIsArray($bundles['PHPUnitService']);
        static::assertSame($bundle, $bundles['PHPUnitService']);
    }

    public function testGetContenaVersion(): void
    {
        $expected = [
            'version' => '6.7.9999999.9999999-dev',
        ];

        $url = '/api/_info/version';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $version = mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3);
        static::assertNotSame('', $version);
        static::assertStringStartsWith($version, $content);
    }

    public function testGetContenaVersionOldVersion(): void
    {
        $expected = [
            'version' => '6.7.9999999.9999999-dev',
        ];

        $url = '/api/v1/_info/version';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $version = mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3);
        static::assertNotSame('', $version);
        static::assertStringStartsWith($version, $content);
    }

    public function testBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'extensions' => [],
                'name' => 'checkout.user.login',
                'class' => UserLoginEvent::class,
                'data' => [
                    'user' => [
                        'type' => 'entity',
                        'entityClass' => UserDefinition::class,
                        'entityName' => 'user',
                    ],
                    'contextToken' => [
                        'type' => 'string',
                        'hiddenFromWebhook' => true,
                    ],
                ],
                'aware' => [
                    ScalarValuesAware::class,
                    lcfirst((new \ReflectionClass(ScalarValuesAware::class))->getShortName()),
                    ChannelAware::class,
                    lcfirst((new \ReflectionClass(ChannelAware::class))->getShortName()),
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    UserAware::class,
                    lcfirst((new \ReflectionClass(UserAware::class))->getShortName()),
                    LogAware::class,
                    lcfirst((new \ReflectionClass(LogAware::class))->getShortName()),
                ],
            ],
            [
                'extensions' => [],
                'name' => 'checkout.order.placed',
                'class' => CheckoutOrderPlacedEvent::class,
                'data' => [
                    'order' => [
                        'type' => 'entity',
                        'entityClass' => OrderDefinition::class,
                        'entityName' => 'order',
                    ],
                ],
                'aware' => [
                    A11yRenderedDocumentAware::class,
                    lcfirst((new \ReflectionClass(A11yRenderedDocumentAware::class))->getShortName()),
                    UserAware::class,
                    lcfirst((new \ReflectionClass(UserAware::class))->getShortName()),
                    UserGroupAware::class,
                    lcfirst((new \ReflectionClass(UserGroupAware::class))->getShortName()),
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    ChannelAware::class,
                    lcfirst((new \ReflectionClass(ChannelAware::class))->getShortName()),
                    OrderAware::class,
                    lcfirst((new \ReflectionClass(OrderAware::class))->getShortName()),
                ],
            ],
            [
                'extensions' => [],
                'name' => 'state_enter.order_delivery.state.shipped_partially',
                'class' => OrderStateMachineStateChangeEvent::class,
                'data' => [
                    'order' => [
                        'type' => 'entity',
                        'entityClass' => OrderDefinition::class,
                        'entityName' => 'order',
                    ],
                ],
                'aware' => [
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    ChannelAware::class,
                    lcfirst((new \ReflectionClass(ChannelAware::class))->getShortName()),
                    OrderAware::class,
                    lcfirst((new \ReflectionClass(OrderAware::class))->getShortName()),
                    UserAware::class,
                    lcfirst((new \ReflectionClass(UserAware::class))->getShortName()),
                    A11yRenderedDocumentAware::class,
                    lcfirst((new \ReflectionClass(A11yRenderedDocumentAware::class))->getShortName()),
                ],
            ],
        ];

        foreach ($expected as $event) {
            $actualEvents = array_values(array_filter($response, static fn ($x) => $x['name'] === $event['name']));
            static::assertNotCount(0, $actualEvents, 'Event with name "' . $event['name'] . '" not found');
            sort($event['aware']);
            sort($actualEvents[0]['aware']);
            static::assertCount(1, $actualEvents);
            static::assertSame($event, $actualEvents[0], $event['name']);
        }
    }

    public function testFlowActionsRoute(): void
    {
        $url = '/api/_info/flow-actions.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'extensions' => [],
                'name' => 'action.add.order.tag',
                'requirements' => [
                    'orderAware',
                ],
                'delayable' => true,
            ],
        ];

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, static fn ($x) => $x['name'] === $action['name']));
            static::assertNotCount(0, $actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertSame($action, $actualActions[0]);
        }
    }

    public function testFlowActionRouteHasAppFlowActions(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId);

        $appId = Uuid::randomHex();
        $this->createApp($appId, $aclRoleId);

        $flowAppId = Uuid::randomHex();
        $this->createAppFlowAction($flowAppId, $appId);

        $url = '/api/_info/flow-actions.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $expected = [
            [
                'extensions' => [],
                'name' => 'telegram.send.message',
                'requirements' => [
                    'orderaware',
                ],
                'delayable' => true,
            ],
        ];

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, static fn ($x) => $x['name'] === $action['name']));
            static::assertNotCount(0, $actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertSame($action, $actualActions[0]);
        }
    }

    public function testMailAwareBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        foreach ($response as $event) {
            if (\in_array($event['name'], ['mail.after.create.message', 'mail.before.send', 'mail.sent'], true)) {
                static::assertNotContains(MailAware::class, $event['aware']);

                continue;
            }

            static::assertContains(MailAware::class, $event['aware'], $event['name']);
        }
    }

    public function testFlowBusinessEventRouteHasAppFlowEvents(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId);

        $appId = Uuid::randomHex();
        $this->createApp($appId, $aclRoleId);

        $flowAppId = Uuid::randomHex();
        $this->createAppFlowEvent($flowAppId, $appId);

        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $expected = [
            [
                'extensions' => [],
                'name' => 'user.wishlist',
                'class' => CustomAppEvent::class,
                'data' => [],
                'aware' => [
                    'mailAware',
                    'userAware',
                ],
            ],
        ];

        foreach ($expected as $event) {
            $actualEvent = array_values(array_filter($response, static function ($x) use ($event) {
                return $x['name'] === $event['name'];
            }));

            static::assertNotCount(0, $actualEvent, 'Event with name "' . $event['name'] . '" not found');
            static::assertCount(1, $actualEvent);
            static::assertSame($event, $actualEvent[0]);
        }
    }

    public function testFetchApiRoutes(): void
    {
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/routes');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $routes = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        foreach ($routes['endpoints'] as $route) {
            static::assertArrayHasKey('path', $route);
            static::assertArrayHasKey('methods', $route);
        }
    }

    public function testFetchMessageStats(): void
    {
        $statsService = $this->getContainer()->get(StatsService::class);
        $statsService->registerMessage(new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . (time() - 2))),
        ]));
        $statsService->registerMessage(new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . (time() - 1))),
        ]));

        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/message-stats.json');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        static::assertJson($content);
        $stats = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($stats);
        static::assertArrayHasKey('enabled', $stats);
        static::assertTrue($stats['enabled']);
        static::assertArrayHasKey('stats', $stats);
        static::assertIsArray($stats['stats']);
        static::assertArrayHasKey('totalMessagesProcessed', $stats['stats']);
        static::assertGreaterThanOrEqual(2, $stats['stats']['totalMessagesProcessed']);
        static::assertArrayHasKey('processedSince', $stats['stats']);
        static::assertInstanceOf(\DateTimeInterface::class, \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $stats['stats']['processedSince']));
        static::assertArrayHasKey('averageTimeInQueue', $stats['stats']);
        static::assertIsFloat($stats['stats']['averageTimeInQueue']);
        static::assertArrayHasKey('messageTypeStats', $stats['stats']);
        static::assertIsArray($stats['stats']['messageTypeStats']);
        static::assertArrayHasKey('type', $stats['stats']['messageTypeStats'][0]);
        static::assertSame('stdClass', $stats['stats']['messageTypeStats'][0]['type']);
        static::assertArrayHasKey('count', $stats['stats']['messageTypeStats'][0]);
    }

    private function createApp(string $appId, string $aclRoleId): void
    {
        $this->connection->insert('app', [
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'flowbuilderactionapp',
            'active' => 1,
            'path' => 'custom/apps/flowbuilderactionapp',
            'version' => '1.0.0',
            'configurable' => 0,
            'app_secret' => 'appSecret',
            'acl_role_id' => Uuid::fromHexToBytes($aclRoleId),
            'integration_id' => $this->getIntegrationId(),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAppFlowAction(string $flowAppId, string $appId): void
    {
        $this->connection->insert('app_flow_action', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'telegram.send.message',
            'badge' => 'Telegram',
            'url' => 'https://example.xyz',
            'delayable' => true,
            'requirements' => json_encode(['orderaware'], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAppFlowEvent(string $flowAppId, string $appId): void
    {
        $this->connection->insert('app_flow_event', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'user.wishlist',
            'aware' => json_encode(['mailAware', 'userAware'], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getIntegrationId(): string
    {
        $integrationId = Uuid::randomBytes();

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'test',
            'secret_access_key' => 'test',
            'label' => 'test',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $integrationId;
    }

    private function createAclRole(string $aclRoleId): void
    {
        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode(['users_and_permissions.viewer'], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
