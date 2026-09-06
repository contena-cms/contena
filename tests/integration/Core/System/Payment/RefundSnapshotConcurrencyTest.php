<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Refund\PaymentRefundStateHandler;
use Contena\Core\System\Payment\Struct\GatewayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\TestCase;

/**
 * Uses committed, uniquely identified fixtures because a second connection must
 * observe them. Every created row is removed in finally, including on failure.
 *
 * @internal
 */
final class RefundSnapshotConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    public function testLockedCurrentStatePreventsDoubleReleaseAfterAnOlderSnapshotWasEstablished(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, $connection->getTransactionNestingLevel());
        $writer = DriverManager::getConnection($connection->getParams());
        $isolation = $connection->getTransactionIsolation();
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $refundId = Uuid::randomHex();
        $otherRefundId = Uuid::randomHex();
        $stateMachineId = Uuid::randomHex();
        $stateId = Uuid::randomHex();
        $code = 'snapshot-' . substr($appId, 0, 16);

        try {
            $registry->getRepository('state_machine')->create([[
                'id' => $stateMachineId, 'technicalName' => $code, 'name' => 'Snapshot fixture',
                'states' => [['id' => $stateId, 'technicalName' => 'created', 'name' => 'Created']],
            ]], $context);
            $registry->getRepository('payment_app')->create([[
                'id' => $appId, 'appCode' => $code, 'appSecret' => 'test-secret', 'name' => 'Snapshot test', 'status' => true,
            ]], $context);
            $registry->getRepository('payment_channel')->create([[
                'id' => $channelId, 'code' => $code, 'name' => 'Snapshot test', 'status' => true, 'sort' => 1,
            ]], $context);
            $registry->getRepository('payment_channel_config')->create([[
                'id' => $configId, 'paymentAppId' => $appId, 'channelId' => $channelId, 'status' => true, 'config' => [],
            ]], $context);
            $registry->getRepository('payment_order')->create([[
                'id' => $orderId, 'paymentAppId' => $appId, 'orderNo' => $orderId, 'externalOrderNo' => $orderId,
                'amount' => 1000, 'refundedAmount' => 700, 'currencyCode' => 'CNY', 'channelCode' => $code,
                'methodCode' => 'h5', 'deviceType' => 'h5', 'subject' => 'Snapshot test', 'channelConfigId' => $configId,
                'stateId' => $stateId,
            ]], $context);
            $registry->getRepository('payment_refund')->create([
                ['id' => $refundId, 'orderId' => $orderId, 'refundNo' => $refundId, 'externalRefundNo' => $refundId, 'refundAmount' => 300, 'channelCode' => $code, 'status' => PaymentRefundStatus::STATUS_PROCESSING],
                ['id' => $otherRefundId, 'orderId' => $orderId, 'refundNo' => $otherRefundId, 'externalRefundNo' => $otherRefundId, 'refundAmount' => 400, 'channelCode' => $code, 'status' => PaymentRefundStatus::STATUS_PROCESSING],
            ], $context);

            $connection->setTransactionIsolation(TransactionIsolationLevel::REPEATABLE_READ);
            $connection->beginTransaction();
            $refund = $registry->getRepository('payment_refund')->search(new Criteria([$refundId]), $context)->getEntities()->first();
            $order = $registry->getRepository('payment_order')->search(new Criteria([$orderId]), $context)->getEntities()->first();
            static::assertInstanceOf(PaymentRefundEntity::class, $refund);
            static::assertInstanceOf(PaymentOrderEntity::class, $order);
            static::assertSame(PaymentRefundStatus::STATUS_PROCESSING, $refund->status);

            // A separate committed notification fails the first refund and releases 300.
            $writer->transactional(static function (Connection $writer) use ($refundId, $orderId): void {
                $writer->update('payment_refund', ['status' => PaymentRefundStatus::STATUS_FAILED], ['id' => Uuid::fromHexToBytes($refundId)]);
                $writer->update('payment_order', ['refunded_amount' => 400], ['id' => Uuid::fromHexToBytes($orderId)]);
            });

            static::assertSame(PaymentRefundStatus::STATUS_PROCESSING, (int) $connection->fetchOne('SELECT status FROM payment_refund WHERE id = ?', [Uuid::fromHexToBytes($refundId)]));
            $result = static::getContainer()->get(PaymentRefundStateHandler::class)->apply($refund, $order, new GatewayResult(PaymentStatus::FAILED), $context);
            $connection->commit();

            static::assertSame(PaymentStatus::FAILED, $result->status);
            static::assertSame(400, (int) $writer->fetchOne('SELECT refunded_amount FROM payment_order WHERE id = ?', [Uuid::fromHexToBytes($orderId)]));
            static::assertSame(PaymentRefundStatus::STATUS_PROCESSING, (int) $writer->fetchOne('SELECT status FROM payment_refund WHERE id = ?', [Uuid::fromHexToBytes($otherRefundId)]));
        } finally {
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->setTransactionIsolation($isolation);
            $writer->close();
            $connection->delete('payment_refund', ['order_id' => Uuid::fromHexToBytes($orderId)]);
            $connection->delete('payment_order', ['id' => Uuid::fromHexToBytes($orderId)]);
            $connection->delete('payment_channel_config', ['id' => Uuid::fromHexToBytes($configId)]);
            $connection->delete('payment_app', ['id' => Uuid::fromHexToBytes($appId)]);
            $connection->delete('payment_channel', ['id' => Uuid::fromHexToBytes($channelId)]);
            $registry->getRepository('state_machine')->delete([['id' => $stateMachineId]], $context);
        }
    }
}
