<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment\OpenApi;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1786016192ContenaBasicData;
use Contena\Core\Migration\V6_8\Migration1788425391AddPaymentNotifyResponseStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\OpenApi\AppNotificationService;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Contena\Core\System\Payment\OpenApi\Util\SignUtil;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
final class AppNotificationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string APP_SECRET = 'outbound-notification-secret';

    private Connection $connection;

    private Context $context;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        new Migration1786016192ContenaBasicData()->update($this->connection);
        new Migration1788425391AddPaymentNotifyResponseStatus()->update($this->connection);
        $this->context = Context::createDefaultContext();
        $this->clock = new MockClock('2026-09-03T10:00:00+08:00');
    }

    public function testDeliversASignedNotificationAndPersistsTheResponse(): void
    {
        $recordId = $this->createPaymentNotification($this->context);
        $sentPayload = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentPayload): MockResponse {
            static::assertSame('POST', $method);
            static::assertSame('https://app.example/payment-notify', $url);
            static::assertSame(0, $options['max_redirects']);
            static::assertSame(10.0, $options['timeout']);
            $sentPayload = json_decode((string) $options['body'], true, flags: \JSON_THROW_ON_ERROR);

            return new MockResponse('accepted', ['http_code' => 204]);
        });

        $delivered = $this->service($client)->deliverPending($this->context);

        static::assertSame(1, $delivered);
        static::assertIsArray($sentPayload);
        static::assertSame($recordId, $sentPayload['notification_id']);
        static::assertSame('notification-app', $sentPayload['app_id']);
        static::assertSame('platform-order', $sentPayload['resource_no']);
        static::assertTrue(SignUtil::verify($sentPayload, self::APP_SECRET, $this->clock->now()->getTimestamp()));

        $record = $this->record($recordId, $this->context);
        static::assertSame(PaymentNotifyRecordStatus::STATUS_SUCCEEDED, $record->status);
        static::assertSame(0, $record->retryCount);
        static::assertSame(204, $record->responseStatus);
        static::assertSame('accepted', $record->responseBody);
        static::assertNull($record->availableAt);
    }

    public function testRetriesFailedResponsesAfterTheBackoff(): void
    {
        $recordId = $this->createPaymentNotification($this->context);
        $client = new MockHttpClient([
            new MockResponse('temporarily unavailable', ['http_code' => 503]),
            new MockResponse('accepted', ['http_code' => 200]),
        ]);
        $service = $this->service($client);

        static::assertSame(1, $service->deliverPending($this->context));
        $record = $this->record($recordId, $this->context);
        static::assertSame(PaymentNotifyRecordStatus::STATUS_PENDING, $record->status);
        static::assertSame(1, $record->retryCount);
        static::assertSame(503, $record->responseStatus);
        static::assertSame($this->clock->now()->getTimestamp() + 60, $record->availableAt?->getTimestamp());

        static::assertSame(0, $service->deliverPending($this->context));
        $this->clock->sleep(60);
        static::assertSame(1, $service->deliverPending($this->context));

        $record = $this->record($recordId, $this->context);
        static::assertSame(PaymentNotifyRecordStatus::STATUS_SUCCEEDED, $record->status);
        static::assertSame(1, $record->retryCount);
        static::assertSame(200, $record->responseStatus);
    }

    public function testReclaimsExpiredProcessingRecordsWithoutCrossingTenantScopes(): void
    {
        $tenantId = $this->createTenant('Outbound payment notification tenant')->id;
        $tenantContext = Context::createTenantContext($tenantId);
        $recordId = $this->createPaymentNotification(
            $tenantContext,
            PaymentNotifyRecordStatus::STATUS_PROCESSING,
            new \DateTimeImmutable('2026-09-03T09:59:00+08:00'),
        );
        $client = new MockHttpClient(new MockResponse('accepted'));
        $service = $this->service($client);

        static::assertSame(0, $service->deliverPending($this->context));
        static::assertSame(0, $service->deliverPending(Context::createTenantContext($this->createTenant('Other payment tenant')->id)));
        static::assertSame(1, $service->deliverPending($tenantContext));
        static::assertSame(PaymentNotifyRecordStatus::STATUS_SUCCEEDED, $this->record($recordId, $tenantContext)->status);

        $this->expectExceptionObject(OpenApiException::invalidRequest('App notifications must be delivered with a platform or tenant context.'));
        $service->deliverPending(Context::createGlobalContext());
    }

    public function testMarksTheRecordFailedAfterTheLastRetry(): void
    {
        $recordId = $this->createPaymentNotification(
            $this->context,
            retryCount: 7,
        );

        static::assertSame(1, $this->service(new MockHttpClient(new MockResponse('rejected', ['http_code' => 500])))
            ->deliverPending($this->context));

        $record = $this->record($recordId, $this->context);
        static::assertSame(PaymentNotifyRecordStatus::STATUS_FAILED, $record->status);
        static::assertSame(7, $record->retryCount);
        static::assertSame(500, $record->responseStatus);
        static::assertNull($record->availableAt);
    }

    private function service(MockHttpClient $client): AppNotificationService
    {
        return new AppNotificationService(
            $this->notifyRecordRepository(),
            $this->connection,
            $client,
            $this->clock,
        );
    }

    /**
     * @return EntityRepository<PaymentNotifyRecordCollection>
     */
    private function notifyRecordRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('payment_notify_record.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function createPaymentNotification(
        Context $context,
        int $status = PaymentNotifyRecordStatus::STATUS_PENDING,
        ?\DateTimeImmutable $availableAt = null,
        int $retryCount = 0,
    ): string {
        $suffix = bin2hex(random_bytes(5));
        $appId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $channelConfigId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $recordId = Uuid::randomHex();

        $this->repository('payment_app')->create([[
            'id' => $appId,
            'appCode' => 'notification-app',
            'appSecret' => self::APP_SECRET,
            'name' => 'Notification app',
            'status' => true,
        ]], $context);
        $this->repository('payment_channel')->create([[
            'id' => $channelId,
            'code' => 'notification-' . $suffix,
            'name' => 'Notification channel',
            'status' => true,
            'sort' => 1,
        ]], $context);
        $this->repository('payment_channel_config')->create([[
            'id' => $channelConfigId,
            'paymentAppId' => $appId,
            'channelId' => $channelId,
            'config' => [],
            'status' => true,
        ]], $context);
        $stateId = static::getContainer()->get(StateMachineRegistry::class)
            ->getStateMachine(PaymentOrderStates::STATE_MACHINE, $context)
            ->getInitialStateId();
        static::assertNotNull($stateId);
        $this->repository('payment_order')->create([[
            'id' => $orderId,
            'paymentAppId' => $appId,
            'orderNo' => 'platform-order-' . $suffix,
            'externalOrderNo' => 'external-order-' . $suffix,
            'amount' => 1000,
            'currencyCode' => 'CNY',
            'channelCode' => 'notification-' . $suffix,
            'methodCode' => 'h5',
            'deviceType' => 'h5',
            'subject' => 'Notification test order',
            'stateId' => $stateId,
            'channelConfigId' => $channelConfigId,
        ]], $context);
        $this->repository('payment_notify_record')->create([[
            'id' => $recordId,
            'orderId' => $orderId,
            'notifyType' => 1,
            'notifyUrl' => 'https://app.example/payment-notify',
            'requestBody' => json_encode([
                'type' => 'payment',
                'resource_no' => 'platform-order',
                'external_resource_no' => 'external-order',
                'status' => 'succeeded',
            ], \JSON_THROW_ON_ERROR),
            'status' => $status,
            'retryCount' => $retryCount,
            'availableAt' => $availableAt,
        ]], $context);

        return $recordId;
    }

    private function record(string $id, Context $context): PaymentNotifyRecordEntity
    {
        $record = $this->repository('payment_notify_record')->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(PaymentNotifyRecordEntity::class, $record);

        return $record;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entity): EntityRepository
    {
        $repository = static::getContainer()->get($entity . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
