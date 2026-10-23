<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserEntity;
use Contena\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Contena\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\Flow\DummyEvent;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(MailStorer::class)]
class MailStorerTest extends TestCase
{
    private MailStorer $storer;

    protected function setUp(): void
    {
        $this->storer = new MailStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(OrderStateMachineStateChangeEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(MailAware::MAIL_STRUCT, $stored);
        static::assertArrayHasKey(MailAware::CHANNEL_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(MailAware::MAIL_STRUCT, $stored);
        static::assertArrayNotHasKey(MailAware::CHANNEL_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $store = [
            'recipients' => ['name' => 'test'],
            'bcc' => 'bcc',
            'cc' => 'cc',
        ];

        $flow = new StorableFlow('test', Context::createDefaultContext(), [MailAware::MAIL_STRUCT => $store]);

        $this->storer->restore($flow);

        static::assertTrue($flow->hasData(MailAware::MAIL_STRUCT));

        static::assertInstanceOf(MailRecipientStruct::class, $flow->getData(MailAware::MAIL_STRUCT));

        static::assertSame('test', $flow->getData(MailAware::MAIL_STRUCT)->getRecipients()['firstName']);
        static::assertSame('bcc', $flow->getData(MailAware::MAIL_STRUCT)->getBcc());
        static::assertSame('cc', $flow->getData(MailAware::MAIL_STRUCT)->getCc());
    }

    public function testRestoreHasDataOrder(): void
    {
        $flow = new StorableFlow('test', Context::createDefaultContext(), [OrderAware::ORDER_ID => Uuid::randomHex()]);
        $user = new OrderUserEntity();
        $user->setId(Uuid::randomHex());
        $user->setName('bar');
        $user->setLastName('foo');
        $user->setEmail('foo@bar.com');
        $order = new OrderEntity();
        $order->setOrderUser($user);
        $order->setChannelId(TestDefaults::CHANNEL);
        $flow->setData(OrderAware::ORDER, $order);

        $this->storer->restore($flow);

        static::assertTrue($flow->hasData(MailAware::MAIL_STRUCT));

        static::assertInstanceOf(MailRecipientStruct::class, $flow->getData(MailAware::MAIL_STRUCT));
        static::assertSame('barfoo', $flow->getData(MailAware::MAIL_STRUCT)->getRecipients()['foo@bar.com']);
        static::assertNull($flow->getData(MailAware::MAIL_STRUCT)->getBcc());
        static::assertNull($flow->getData(MailAware::MAIL_STRUCT)->getCc());
    }

    public function testRestoreHasDataUser(): void
    {
        $flow = new StorableFlow('test', Context::createDefaultContext(), [OrderAware::ORDER_ID => Uuid::randomHex()]);
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setName('bar');
        $user->setLastName('foo');
        $user->setEmail('foo@bar.com');
        $user->setChannelId(TestDefaults::CHANNEL);

        $flow->setData(UserAware::CUSTOMER, $user);

        $this->storer->restore($flow);

        static::assertTrue($flow->hasData(MailAware::MAIL_STRUCT));

        static::assertInstanceOf(MailRecipientStruct::class, $flow->getData(MailAware::MAIL_STRUCT));
        static::assertSame('barfoo', $flow->getData(MailAware::MAIL_STRUCT)->getRecipients()['foo@bar.com']);
        static::assertNull($flow->getData(MailAware::MAIL_STRUCT)->getBcc());
        static::assertNull($flow->getData(MailAware::MAIL_STRUCT)->getCc());
    }
}

/**
 * @internal
 */
class MailEvent extends DummyEvent implements MailAware
{
    public function __construct(private readonly MailRecipientStruct $recipients)
    {
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getChannelId(): ?string
    {
        return TestDefaults::CHANNEL;
    }
}
