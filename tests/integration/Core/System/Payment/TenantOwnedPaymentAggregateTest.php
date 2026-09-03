<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1786016192ContenaBasicData;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentTransactionStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TenantOwnedPaymentAggregateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $channelMethodId;

    private string $channelId;

    private string $orderStateId;

    private string $tenantA;

    private string $tenantB;

    private string $transactionStateId;

    private string $transferStateId;

    protected function setUp(): void
    {
        new Migration1786016192ContenaBasicData()->update(static::getContainer()->get(Connection::class));

        $this->tenantA = $this->createTenant('Payment aggregate tenant A')->id;
        $this->tenantB = $this->createTenant('Payment aggregate tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];
        [$this->channelId, $this->channelMethodId] = $this->createPlatformChannelMethod();

        $registry = static::getContainer()->get(StateMachineRegistry::class);
        $context = Context::createDefaultContext();
        $orderStateId = $registry->getStateMachine(PaymentOrderStates::STATE_MACHINE, $context)->getInitialStateId();
        $transactionStateId = $registry->getStateMachine(PaymentTransactionStates::STATE_MACHINE, $context)->getInitialStateId();
        $transferStateId = $registry->getStateMachine(PaymentTransferStates::STATE_MACHINE, $context)->getInitialStateId();
        static::assertIsString($orderStateId);
        static::assertIsString($transactionStateId);
        static::assertIsString($transferStateId);
        $this->orderStateId = $orderStateId;
        $this->transactionStateId = $transactionStateId;
        $this->transferStateId = $transferStateId;
    }

    public function testReadAndWriteMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createPaymentAppAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        foreach ($this->contexts as $scope => $context) {
            foreach ($this->entityIdMap() as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('paymentAppId', array_column($ids, 'app')));
            static::assertCount(
                $expectedCounts[$scope],
                $this->repository('payment_app_translation')->searchIds($criteria, $context)->getIds(),
                'Unexpected payment_app_translation rows for ' . $scope,
            );
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $this->assertStoredTenant('payment_app_translation', 'payment_app_id', $scopeIds['app'], $expectedTenants[$scope]);
            foreach ($this->entityIdMap() as $entityName => $idKey) {
                $this->assertStoredTenant($entityName, 'id', $scopeIds[$idKey], $expectedTenants[$scope]);
            }
        }

        $tenantA = $ids['tenant-a'];
        $this->repository('payment_app_translation')->update([[
            'paymentAppId' => $tenantA['app'],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'name' => 'Updated tenant A app',
        ]], $this->contexts['tenant-a']);
        foreach ($this->entityUpdateMap($tenantA, 'tenant-a') as $entityName => $payload) {
            $this->repository($entityName)->update([$payload], $this->contexts['tenant-a']);
        }

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertWriteRejected(
                fn () => $this->repository('payment_app_translation')->update([[
                    'paymentAppId' => $tenantA['app'],
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => 'Rejected ' . $scope,
                ]], $this->contexts[$scope]),
                'Expected payment_app_translation write protection for ' . $scope,
            );
            foreach ($this->entityUpdateMap($tenantA, $scope) as $entityName => $payload) {
                $this->assertWriteRejected(
                    fn () => $this->repository($entityName)->update([$payload], $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }
        }

        $this->assertWriteRejected(
            fn () => $this->repository('payment_app_channel_method')->create([[
                'id' => Uuid::randomHex(),
                'paymentAppId' => $ids['tenant-b']['app'],
                'channelMethodId' => $this->channelMethodId,
                'status' => true,
                'sort' => 10,
            ]], $this->contexts['tenant-a']),
            'Expected a payment method assignment referencing another tenant app to be rejected',
        );
    }

    /**
     * @return array{string, string}
     */
    private function createPlatformChannelMethod(): array
    {
        $suffix = \bin2hex(\random_bytes(8));
        $channelId = Uuid::randomHex();
        $methodId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->repository('payment_channel')->create([[
            'id' => $channelId,
            'code' => 'test-' . $suffix,
            'name' => 'Tenant matrix channel',
            'status' => true,
            'sort' => 10,
        ]], $context);
        $this->repository('payment_channel_method')->create([[
            'id' => $methodId,
            'channelId' => $channelId,
            'methodCode' => 'test-' . $suffix,
            'name' => 'Tenant matrix method',
            'status' => true,
            'sort' => 10,
        ]], $context);

        return [$channelId, $methodId];
    }

    /**
     * @return array{app: string, method: string, config: string, recurring: string, order: string, refund: string, transfer: string, transaction: string, channelNotify: string, notify: string}
     */
    private function createPaymentAppAggregate(string $scope, Context $context): array
    {
        $appId = Uuid::randomHex();
        $methodId = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $recurringId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $refundId = Uuid::randomHex();
        $transferId = Uuid::randomHex();
        $transactionId = Uuid::randomHex();
        $channelNotifyId = Uuid::randomHex();
        $notifyId = Uuid::randomHex();
        $number = $scope . '-' . \bin2hex(\random_bytes(4));

        $this->repository('payment_app')->create([[
            'id' => $appId,
            'appCode' => 'tenant-matrix-' . $scope . '-' . \bin2hex(\random_bytes(4)),
            'appSecret' => 'test-secret',
            'name' => 'Tenant matrix app ' . $scope,
            'status' => true,
        ]], $context);
        $this->repository('payment_app_channel_method')->create([[
            'id' => $methodId,
            'paymentAppId' => $appId,
            'channelMethodId' => $this->channelMethodId,
            'status' => true,
            'sort' => 10,
        ]], $context);
        $this->repository('payment_channel_config')->create([[
            'id' => $configId,
            'paymentAppId' => $appId,
            'channelId' => $this->channelId,
            'status' => true,
        ]], $context);
        $this->repository('payment_recurring')->create([[
            'id' => $recurringId,
            'paymentAppId' => $appId,
            'recurringNo' => 'recurring-' . $number,
            'externalRecurringNo' => 'external-' . $number,
            'channelCode' => 'tenant-matrix',
            'channelConfigId' => $configId,
        ]], $context);
        $this->repository('payment_order')->create([[
            'id' => $orderId,
            'paymentAppId' => $appId,
            'orderNo' => 'order-' . $number,
            'externalOrderNo' => 'external-' . $number,
            'amount' => 1000,
            'currencyCode' => 'CNY',
            'channelCode' => 'tenant-matrix',
            'methodCode' => 'test',
            'deviceType' => 'test',
            'subject' => 'Tenant matrix order',
            'stateId' => $this->orderStateId,
            'channelConfigId' => $configId,
            'recurringId' => $recurringId,
        ]], $context);
        $this->repository('payment_refund')->create([[
            'id' => $refundId,
            'refundNo' => 'refund-' . $number,
            'orderId' => $orderId,
            'externalRefundNo' => 'external-' . $number,
            'refundAmount' => 100,
            'channelCode' => 'tenant-matrix',
        ]], $context);
        $this->repository('payment_transfer')->create([[
            'id' => $transferId,
            'paymentAppId' => $appId,
            'transferNo' => 'transfer-' . $number,
            'externalTransferNo' => 'external-' . $number,
            'amount' => 100,
            'currencyCode' => 'CNY',
            'channelCode' => 'tenant-matrix',
            'channelConfigId' => $configId,
            'stateId' => $this->transferStateId,
            'payee' => 'payee-' . $scope,
            'payeeName' => 'Tenant matrix payee',
        ]], $context);
        $this->repository('payment_order_transaction')->create([[
            'id' => $transactionId,
            'orderId' => $orderId,
            'transactionNo' => 'transaction-' . $number,
            'type' => 'create',
            'channelCode' => 'tenant-matrix',
            'methodCode' => 'test',
            'refundId' => $refundId,
            'amount' => 100,
            'stateId' => $this->transactionStateId,
        ]], $context);
        $this->repository('payment_channel_notify_record')->create([[
            'id' => $channelNotifyId,
            'channelCode' => 'tenant-matrix',
            'channelConfigId' => $configId,
            'notificationKey' => hash('sha256', $number),
            'orderId' => $orderId,
            'refundId' => $refundId,
            'transferId' => $transferId,
            'recurringId' => $recurringId,
            'notifyType' => 1,
        ]], $context);
        $this->repository('payment_notify_record')->create([[
            'id' => $notifyId,
            'orderId' => $orderId,
            'refundId' => $refundId,
            'transferId' => $transferId,
            'recurringId' => $recurringId,
            'notifyType' => 1,
            'notifyUrl' => 'https://example.invalid/payment-notify',
        ]], $context);

        return [
            'app' => $appId,
            'method' => $methodId,
            'config' => $configId,
            'recurring' => $recurringId,
            'order' => $orderId,
            'refund' => $refundId,
            'transfer' => $transferId,
            'transaction' => $transactionId,
            'channelNotify' => $channelNotifyId,
            'notify' => $notifyId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function entityIdMap(): array
    {
        return [
            'payment_app' => 'app',
            'payment_app_channel_method' => 'method',
            'payment_channel_config' => 'config',
            'payment_recurring' => 'recurring',
            'payment_order' => 'order',
            'payment_refund' => 'refund',
            'payment_transfer' => 'transfer',
            'payment_order_transaction' => 'transaction',
            'payment_channel_notify_record' => 'channelNotify',
            'payment_notify_record' => 'notify',
        ];
    }

    /**
     * @param array{app: string, method: string, config: string, recurring: string, order: string, refund: string, transfer: string, transaction: string, channelNotify: string, notify: string} $ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function entityUpdateMap(array $ids, string $value): array
    {
        return [
            'payment_app' => ['id' => $ids['app'], 'status' => false],
            'payment_app_channel_method' => ['id' => $ids['method'], 'customFields' => ['matrix' => $value]],
            'payment_channel_config' => ['id' => $ids['config'], 'customFields' => ['matrix' => $value]],
            'payment_recurring' => ['id' => $ids['recurring'], 'customFields' => ['matrix' => $value]],
            'payment_order' => ['id' => $ids['order'], 'customFields' => ['matrix' => $value]],
            'payment_refund' => ['id' => $ids['refund'], 'customFields' => ['matrix' => $value]],
            'payment_transfer' => ['id' => $ids['transfer'], 'customFields' => ['matrix' => $value]],
            'payment_order_transaction' => ['id' => $ids['transaction'], 'customFields' => ['matrix' => $value]],
            'payment_channel_notify_record' => ['id' => $ids['channelNotify'], 'customFields' => ['matrix' => $value]],
            'payment_notify_record' => ['id' => $ids['notify'], 'customFields' => ['matrix' => $value]],
        ];
    }

    private function assertStoredTenant(string $table, string $idColumn, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
