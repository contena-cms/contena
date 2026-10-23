<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\AclRoleLoader;
use Contena\Core\Framework\Api\Acl\AclRoles;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\Event\AppFlowActionEvent;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Hmac\RequestSigner;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\App\Payload\Source;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Authorization\Policy\AppEventPolicy;
use Contena\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Contena\Core\Framework\Webhook\Authorization\Policy\PrivilegePolicy;
use Contena\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Contena\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Contena\Core\Framework\Webhook\Message\WebhookEventMessage;
use Contena\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Contena\Core\Framework\Webhook\Outbox\OutboxEntry;
use Contena\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Contena\Core\Framework\Webhook\Service\WebhookClient;
use Contena\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Contena\Core\Framework\Webhook\Service\WebhookLoader;
use Contena\Core\Framework\Webhook\Service\WebhookManager;
use Contena\Core\Framework\Webhook\Service\WebhookRequest;
use Contena\Core\Framework\Webhook\Webhook;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(WebhookManager::class)]
#[DisabledFeatures(['WEBHOOKS_REWORK'])]
class WebhookManagerTest extends TestCase
{
    private WebhookLoader&MockObject $webhookLoader;

    private AclRoleLoader&MockObject $aclRoleLoader;

    private MockHandler $clientMock;

    private WebhookClient $webhookClient;

    private HookableEventFactory&MockObject $eventFactory;

    private CollectingMessageBus $bus;

    private WebhookOutboxStore&MockObject $webhookOutboxStore;

    protected function setUp(): void
    {
        $this->webhookLoader = $this->createMock(WebhookLoader::class);
        $this->aclRoleLoader = $this->createMock(AclRoleLoader::class);
        $this->clientMock = new MockHandler([new Response(200, [], '{}')]);
        $stack = HandlerStack::create($this->clientMock);
        $stack->push(new AuthMiddleware('6.7.0', static::createStub(AppLocaleProvider::class)));
        $guzzle = new Client(['handler' => $stack]);
        $this->webhookClient = new WebhookClient($guzzle, new NativeClock());
        $this->eventFactory = $this->createMock(HookableEventFactory::class);
        $this->bus = new CollectingMessageBus();
        $this->webhookOutboxStore = $this->createMock(WebhookOutboxStore::class);
        $this->webhookOutboxStore->method('markRunning')->willReturn(new OutboxEntry(
            webhookEventId: 'stub',
            sequence: 1,
            executionCount: 1,
            deliveryStatus: 'running',
        ));
    }

    public function testDispatchesTwoConsecutiveEventsCorrectly(): void
    {
        $event1 = new AppFlowActionEvent('foobar', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']);
        $event2 = new class('foobar.event', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']) extends AppFlowActionEvent {};

        $this->eventFactory
            ->expects($this->exactly(2))
            ->method('createHookablesFor')
            ->willReturn([$event1], [$event2]);

        // only the second dispatch matches a webhook and is delivered synchronously
        $this->webhookOutboxStore->expects($this->once())->method('markSuccess');

        $webhookManager = $this->getWebhookManager(true);
        $webhookManager->dispatch($event1);
        $request = $this->clientMock->getLastRequest();
        static::assertNull($request);

        $webhook = $this->prepareWebhook($event2->getName());
        $this->assertSyncWebhookIsSent($webhook, $event2, $webhookManager);
    }

    public function testDispatchWithWebhooksSync(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook($event->getName());

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::anything(),
            );

        $this->assertSyncWebhookIsSent($webhook, $event);
    }

    public function testSyncDispatchMarksFailedOnNonUtf8FailureBody(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName());

        $this->clientMock->reset();
        $this->clientMock->append(new Response(500, [], pack('C*', 0xB1)));

        $this->webhookOutboxStore->expects($this->once())->method('markFailed')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 500),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markSuccess');

        $this->getWebhookManager(true)->dispatch($event);
    }

    public function testSyncDispatchMarksSuccessOnNonUtf8ResponseHeaders(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName());

        $this->clientMock->reset();
        $this->clientMock->append(new Response(200, ['x-bad' => pack('C*', 0xB1)], '{}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 200),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');

        $this->getWebhookManager(true)->dispatch($event);
    }

    public function testDispatchWithWebhooksAsync(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook($event->getName());

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertArrayHasKey('source', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['source']['eventId']);
        static::assertEquals([
            'foo' => 'bar',
            'source' => [
                'url' => 'https://example.com',
                'appVersion' => $webhook->appVersion,
                'shopId' => 'foobar',
                'action' => $event->getName(),
                'inAppPurchases' => null,
            ],
        ], $payload);

        static::assertSame($message->getLanguageId(), Defaults::LANGUAGE_SYSTEM);
        static::assertSame($message->getAppId(), $webhook->appId);
        static::assertSame($message->getSecret(), $webhook->appSecret);
        static::assertSame($message->getContenaVersion(), '0.0.0');
        static::assertSame($message->getUrl(), 'https://foo.bar');
        static::assertSame($message->getWebhookId(), $webhook->id);
        static::assertSame(['x-test-header' => 'test-header-val'], $message->getWebhookHeaders());
    }

    public function testWebhookSettingForLiveVersionOnlyIsIgnoredIfEventTypeDoesNotMatch(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName(), true);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksForLiveVersionOnlyAreCalledIfPayloadHasLiveVersion(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('blog.written', true);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();

        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksAreNotDispatchedIfPrivilegesAreMissing(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('blog.written', true, []);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);
        $messages = $this->bus->getMessages();
        static::assertCount(0, $messages);
    }

    public function testWebhookCacheKeepsInactiveAppStateUntilCleared(): void
    {
        $event = new AppFlowActionEvent('commercial_license.provided', [], ['foo' => 'bar']);
        $inactiveWebhook = $this->getWebhook($event->getName(), appActive: false);
        $activeWebhook = $this->getWebhook($event->getName());

        $this->eventFactory
            ->expects($this->exactly(3))
            ->method('createHookablesFor')
            ->with($event)
            ->willReturn([$event]);

        $this->webhookLoader->expects($this->exactly(2))
            ->method('getWebhooks')
            ->willReturn([$inactiveWebhook], [$activeWebhook]);

        $this->aclRoleLoader
            ->expects($this->atLeastOnce())
            ->method('getPrivilegesForRoles')
            ->willReturnCallback(static function (array $roleIds): array {
                $privileges = [];
                foreach ($roleIds as $roleId) {
                    $privileges[$roleId] = new AclPrivilegeCollection([]);
                }

                return $privileges;
            });

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $webhookManager = $this->getWebhookManager(false);

        $webhookManager->dispatch($event);
        static::assertCount(0, $this->bus->getMessages());

        $webhookManager->dispatch($event);
        static::assertCount(0, $this->bus->getMessages());

        $webhookManager->clearInternalWebhookCache();
        $webhookManager->dispatch($event);
        static::assertCount(1, $this->bus->getMessages());
    }

    public function testWebhooksForLiveVersionOnlyAreIgnoredIfPayloadHasDifferentVersion(): void
    {
        $event = $this->prepareHookableEvent([
            [
                'id' => Uuid::randomHex(),
                'versionId' => Uuid::randomHex(),
            ],
        ]);

        $this->prepareWebhook('blog.written', true, resolvesPrivileges: false);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(0, $messages);
    }

    public function testWebhooksForLiveVersionOnlyAreSentIfPayloadDoesNotHaveAnyVersionId(): void
    {
        $entityRepository = new StaticEntityRepository([], new UserDefinition());

        $event = $entityRepository->create([
            [
                'id' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        $eventByEntityName = $event->getEventByEntityName('user');
        static::assertInstanceOf(EntityWrittenEvent::class, $eventByEntityName);
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $this->eventFactory->expects($this->once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        $this->prepareWebhook('user.written', true, ['user:read']);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksAreCalledForNonLiveVersionConfig(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('blog.written');

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testPayloadOfWebhookForLiveVersionOnlyIsFiltered(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $payloads = [
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ];

        $event = $this->prepareHookableEvent($payloads);
        $this->prepareWebhook('blog.written', true);

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertCount(1, $payload['data']['payload']);
        static::assertNotFalse(json_encode($payload));
        static::assertStringContainsString($firstId, json_encode($payload));
        static::assertStringNotContainsString($secondId, json_encode($payload));
    }

    public function testPayloadIsLeftUnchangedForNonLiveVersionConfig(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $payloads = [
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ];

        $event = $this->prepareHookableEvent($payloads);
        $this->prepareWebhook('blog.written');

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertCount(2, $payload['data']['payload']);
        static::assertNotFalse(json_encode($payload));
        static::assertStringContainsString($firstId, json_encode($payload));
        static::assertStringContainsString($secondId, json_encode($payload));
    }

    private function assertSyncWebhookIsSent(Webhook $webhook, AppFlowActionEvent $event, ?WebhookManager $webhookManager = null): void
    {
        $expectedRequest = new Request(
            'POST',
            $webhook->url,
            [
                'x-test-header' => 'test-header-val',
                'Content-Type' => 'application/json',
                'sw-version' => '0.0.0',
                'sw-context-language' => [Defaults::LANGUAGE_SYSTEM],
                'sw-admin_user-language' => [''],
            ],
            json_encode([
                'foo' => 'bar',
                'source' => [
                    'url' => 'https://example.com',
                    'appVersion' => $webhook->appVersion,
                    'shopId' => 'foobar',
                    'action' => $event->getName(),
                    'inAppPurchases' => null,
                    'sequence' => 1,
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        $webhookManager = $webhookManager ?? $this->getWebhookManager(true);
        $webhookManager->dispatch($event);

        $request = $this->clientMock->getLastRequest();

        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('foo.bar', $request->getUri()->getHost());

        $headers = $request->getHeaders();
        static::assertArrayHasKey(RequestSigner::CONTENA_SHOP_SIGNATURE, $headers);
        static::assertArrayHasKey('X-Contena-Event-Id', $headers);
        static::assertArrayHasKey('X-Contena-Sequence', $headers);
        static::assertArrayHasKey('X-Contena-Attempt', $headers);
        static::assertSame(['1'], $headers['X-Contena-Sequence']);
        static::assertSame(['0'], $headers['X-Contena-Attempt']);
        unset(
            $headers[RequestSigner::CONTENA_SHOP_SIGNATURE],
            $headers['Content-Length'],
            $headers['AdminUser-Agent'],
            $headers['X-Contena-Event-Id'],
            $headers['X-Contena-Sequence'],
            $headers['X-Contena-Attempt'],
        );
        static::assertEquals($expectedRequest->getHeaders(), $headers);

        $expectedContents = json_decode($expectedRequest->getBody()->getContents(), true);
        $contents = json_decode($request->getBody()->getContents(), true);
        static::assertIsArray($contents);
        static::assertArrayHasKey('timestamp', $contents);
        static::assertArrayHasKey('source', $contents);
        static::assertArrayHasKey('eventId', $contents['source']);
        unset($contents['timestamp'], $contents['source']['eventId']);
        static::assertEquals($expectedContents, $contents);
    }

    private function prepareEvent(): AppFlowActionEvent
    {
        $event = new AppFlowActionEvent('foobar', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']);

        $this->eventFactory
            ->expects($this->once())
            ->method('createHookablesFor')
            ->with($event)
            ->willReturn([$event]);

        return $event;
    }

    /**
     * @param list<array{id: string, versionId: string}>|null $payloads
     */
    private function prepareHookableEvent(?array $payloads = null): Event
    {
        $entityRepository = new StaticEntityRepository([], new BlogDefinition());

        $event = $entityRepository->create($payloads ?? [
            [
                'id' => Uuid::randomHex(),
                'versionId' => Defaults::LIVE_VERSION,
            ],
        ], Context::createDefaultContext());

        $eventByEntityName = $event->getEventByEntityName('blog');
        static::assertInstanceOf(EntityWrittenEvent::class, $eventByEntityName);
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $this->eventFactory->expects($this->once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        return $event;
    }

    /**
     * @param list<string> $acl
     */
    private function prepareWebhook(string $eventName, bool $onlyLiveVersion = false, array $acl = ['blog:read'], bool $resolvesPrivileges = true): Webhook
    {
        $webhook = $this->getWebhook($eventName, $onlyLiveVersion);
        $roleId = $webhook->roles->roleIds[0];

        $this->webhookLoader->expects($this->once())
            ->method('getWebhooks')
            ->willReturn([$webhook]);

        $this->aclRoleLoader
            ->expects($resolvesPrivileges ? $this->once() : $this->never())
            ->method('getPrivilegesForRoles')
            ->with([$roleId])
            ->willReturn([$roleId => new AclPrivilegeCollection($acl)]);

        return $webhook;
    }

    private function getWebhookManager(bool $isAdminWorkerEnabled): WebhookManager
    {
        $appPayloadServiceHelper = static::createStub(AppPayloadServiceHelper::class);
        $appPayloadServiceHelper->method('buildSource')->willReturn(new Source('https://example.com', 'foobar', '0.0.0'));
        $appPayloadServiceHelper->method('createWebhookRequest')->willReturnCallback($this->buildWebhookRequest(...));

        $deliveryService = static::createStub(WebhookDeliveryService::class);
        $deliveryService->method('buildRequest')->willReturnCallback(
            fn (WebhookEventMessage $message, OutboxEntry $entry): WebhookRequest => $this->buildWebhookRequestFromMessage($message, $entry, $appPayloadServiceHelper),
        );

        return new WebhookManager(
            $this->webhookLoader,
            $this->eventFactory,
            static::createStub(AppLocaleProvider::class),
            $appPayloadServiceHelper,
            $this->webhookClient,
            $this->bus,
            'https://example.com',
            '0.0.0',
            $isAdminWorkerEnabled,
            $deliveryService,
            $this->webhookOutboxStore,
            new PolicyRegistry([new AppEventPolicy(), new PrivilegePolicy($this->aclRoleLoader)]),
        );
    }

    private function buildWebhookRequestFromMessage(
        WebhookEventMessage $message,
        OutboxEntry $entry,
        AppPayloadServiceHelper $helper,
    ): WebhookRequest {
        $payload = $message->getPayload();
        if (isset($payload['source']) && \is_array($payload['source'])) {
            $payload['source']['sequence'] = $entry->sequence;
        }

        $headers = $message->getWebhookHeaders();
        $headers[WebhookDeliveryService::HEADER_EVENT_ID] = $message->getWebhookEventId();
        $headers[WebhookDeliveryService::HEADER_SEQUENCE] = (string) $entry->sequence;
        $headers[WebhookDeliveryService::HEADER_ATTEMPT] = (string) max(0, $entry->executionCount - 1);

        return $helper->createWebhookRequest(
            $payload,
            $message->getUrl(),
            $message->getContenaVersion(),
            WebhookClient::CONNECT_TIMEOUT,
            WebhookClient::REQUEST_TIMEOUT,
            $message->getSecret(),
            $message->getLanguageId(),
            $message->getUserLocale(),
            $headers,
        );
    }

    /**
     * Minimal stand-in for AppPayloadServiceHelper::createWebhookRequest.
     * The real method is unit-tested in AppPayloadServiceHelperTest; here we only
     * need a valid WebhookRequest so the Guzzle MockHandler receives a sendable request.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $webhookHeaders
     */
    private function buildWebhookRequest(
        array $payload,
        string $url,
        string $contenaVersion,
        int $connectionTimeout,
        int $requestTimeout,
        ?string $secret = null,
        ?string $languageId = null,
        ?string $adminUserLocale = null,
        array $webhookHeaders = [],
    ): WebhookRequest {
        $payload['timestamp'] = time();
        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json', 'sw-version' => $contenaVersion, ...$webhookHeaders];
        if ($languageId !== null && $adminUserLocale !== null) {
            $headers[AuthMiddleware::CONTENA_CONTEXT_LANGUAGE] = $languageId;
            $headers[AuthMiddleware::CONTENA_USER_LANGUAGE] = $adminUserLocale;
        }

        $options = ['connect_timeout' => $connectionTimeout, 'timeout' => $requestTimeout];
        if ($secret !== null) {
            $options[AuthMiddleware::APP_REQUEST_TYPE] = [AuthMiddleware::APP_SECRET => $secret];
        }

        return new WebhookRequest(new Request('POST', $url, $headers, $jsonPayload), $headers, $jsonPayload, time(), $options);
    }

    private function getWebhook(string $eventName, bool $onlyLiveVersion = false, bool $appActive = true): Webhook
    {
        return new Webhook(
            Uuid::randomHex(),
            'Cool Webhook',
            $eventName,
            'https://foo.bar',
            $onlyLiveVersion,
            Uuid::randomHex(),
            'Cool App',
            'local',
            $appActive,
            '0.0.0',
            'verysecret',
            roles: new AclRoles(false, [Uuid::randomHex()]),
        );
    }
}
