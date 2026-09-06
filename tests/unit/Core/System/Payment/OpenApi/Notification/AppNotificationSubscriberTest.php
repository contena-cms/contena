<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\OpenApi\Notification;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Event\PaymentResultAppliedEvent;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationSubscriber;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppNotificationSubscriber::class)]
#[CoversClass(PaymentResultAppliedEvent::class)]
final class AppNotificationSubscriberTest extends TestCase
{
    #[DataProvider('notificationEnvelopes')]
    public function testProjectsEachBusinessIntoItsApplicationEnvelope(string $entityName, Entity $entity, int $notifyType, string $type, string $association): void
    {
        $id = Uuid::randomHex();
        $entity->assign(['id' => $id]);
        $context = Context::createTenantContext(Uuid::randomHex());
        $source = StaticEntityRepository::of(EntityCollection::class, [static function (Criteria $criteria, Context $actualContext) use ($entityName, $entity, $id, $context): EntityCollection {
            static::assertSame($context, $actualContext);
            static::assertSame([$id], $criteria->getIds());
            static::assertSame($entityName === 'payment_refund', $criteria->hasAssociation('order'));

            return new EntityCollection([$entity]);
        }]);
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($source);
        $outbox = $this->createMock(EntityRepository::class);
        $outbox->expects($this->once())->method('create')->with(
            static::callback(static function (array $rows) use ($notifyType, $type, $association, $id): bool {
                static::assertCount(1, $rows);
                static::assertTrue(Uuid::isValid($rows[0]['id']));
                static::assertSame($id, $rows[0][$association]);
                static::assertSame($notifyType, $rows[0]['notifyType']);
                static::assertSame('https://app.example/notify', $rows[0]['notifyUrl']);
                static::assertSame(PaymentNotifyRecordStatus::STATUS_PENDING, $rows[0]['status']);
                static::assertSame(0, $rows[0]['retryCount']);
                static::assertSame([
                    'type' => $type,
                    'resource_no' => 'internal-1',
                    'external_resource_no' => 'external-1',
                    'status' => PaymentStatus::SUCCEEDED,
                    'result_code' => 'SUCCESS',
                    'result_message' => 'Accepted',
                ], json_decode($rows[0]['requestBody'], true, flags: \JSON_THROW_ON_ERROR));

                return true;
            }),
            static::identicalTo($context),
        );

        new AppNotificationSubscriber($outbox, $registry)->enqueue(new PaymentResultAppliedEvent(
            new PaymentEntityReference($entityName, $id),
            $context,
            new PaymentResult(PaymentStatus::SUCCEEDED, resultCode: 'SUCCESS', resultMessage: 'Accepted'),
        ));
    }

    /**
     * @return iterable<string, array{string, Entity, int, string, string}>
     */
    public static function notificationEnvelopes(): iterable
    {
        $order = new PaymentOrderEntity()->assign(['orderNo' => 'internal-1', 'externalOrderNo' => 'external-1', 'notifyUrl' => 'https://app.example/notify']);
        yield 'incoming payment' => ['payment_order', $order, PaymentNotificationTypes::PAYMENT, 'payment', 'orderId'];
        yield 'refund uses original payment destination' => ['payment_refund', new PaymentRefundEntity()->assign(['refundNo' => 'internal-1', 'externalRefundNo' => 'external-1', 'order' => $order]), PaymentNotificationTypes::REFUND, 'refund', 'refundId'];
        yield 'transfer' => ['payment_transfer', new PaymentTransferEntity()->assign(['transferNo' => 'internal-1', 'externalTransferNo' => 'external-1', 'notifyUrl' => 'https://app.example/notify']), PaymentNotificationTypes::TRANSFER, 'transfer', 'transferId'];
        yield 'subscription agreement' => ['payment_recurring', new PaymentRecurringEntity()->assign(['recurringNo' => 'internal-1', 'externalRecurringNo' => 'external-1', 'notifyUrl' => 'https://app.example/notify']), PaymentNotificationTypes::SUBSCRIPTION, 'subscription', 'recurringId'];
    }

    public function testUnknownPluginEntityDoesNotEnterTheOpenApiProtocol(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');
        $outbox = StaticEntityRepository::of(PaymentNotifyRecordCollection::class);

        new AppNotificationSubscriber($outbox, $registry)->enqueue(new PaymentResultAppliedEvent(new PaymentEntityReference('plugin_invoice', Uuid::randomHex()), Context::createDefaultContext(), new PaymentResult()));

        static::assertSame([], $outbox->creates);
    }

    public function testAbsentDestinationDoesNotCreateANotification(): void
    {
        $id = Uuid::randomHex();
        $entity = new PaymentOrderEntity()->assign(['id' => $id, 'notifyUrl' => null]);
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn(StaticEntityRepository::of(EntityCollection::class, [new EntityCollection([$entity])]));
        $outbox = StaticEntityRepository::of(PaymentNotifyRecordCollection::class);

        new AppNotificationSubscriber($outbox, $registry)->enqueue(new PaymentResultAppliedEvent(new PaymentEntityReference('payment_order', $id), Context::createDefaultContext(), new PaymentResult()));

        static::assertSame([], $outbox->creates);
    }

    public function testMissingScopedEntityFailsInsteadOfCreatingAnIncompleteEnvelope(): void
    {
        $id = Uuid::randomHex();
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn(StaticEntityRepository::of(EntityCollection::class, [new EntityCollection()]));
        $outbox = StaticEntityRepository::of(PaymentNotifyRecordCollection::class);

        $this->expectExceptionObject(PaymentException::notificationResourceNotFound($id));
        new AppNotificationSubscriber($outbox, $registry)->enqueue(new PaymentResultAppliedEvent(new PaymentEntityReference('payment_order', $id), Context::createDefaultContext(), new PaymentResult()));
    }
}
