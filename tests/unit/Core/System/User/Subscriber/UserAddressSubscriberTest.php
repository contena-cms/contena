<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Channel\ChannelUserAddressEntity;
use Contena\Core\System\User\Subscriber\UserAddressSubscriber;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserEvents;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(UserAddressSubscriber::class)]
class UserAddressSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = UserAddressSubscriber::getSubscribedEvents();

        static::assertArrayHasKey('channel.' . UserEvents::CUSTOMER_ADDRESS_LOADED_EVENT, $events);
        static::assertArrayHasKey('channel.user_address.partial_loaded', $events);
    }

    public function testChannelLoadedDoesNothingWithoutUser(): void
    {
        $subscriber = new UserAddressSubscriber();

        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $address = new ChannelUserAddressEntity();
        $address->setId(Uuid::randomHex());

        /** @var ChannelEntityLoadedEvent<UserAddressEntity|PartialEntity> $event */
        $event = new ChannelEntityLoadedEvent(
            new UserAddressDefinition(),
            [$address],
            $context
        );

        $subscriber->channelLoaded($event);

        static::assertFalse($address->isDefaultBillingAddress());
        static::assertFalse($address->isDefaultShippingAddress());
    }

    public function testChannelLoadedAssignsDefaults(): void
    {
        $subscriber = new UserAddressSubscriber();
        $billingId = Uuid::randomHex();
        $shippingId = Uuid::randomHex();
        $otherId = Uuid::randomHex();

        $user = new UserEntity();
        $user->setDefaultBillingAddressId($billingId);
        $user->setDefaultShippingAddressId($shippingId);

        $context = Generator::generateChannelContext(user: $user);

        $billingAddress = new ChannelUserAddressEntity();
        $billingAddress->setId($billingId);

        $shippingAddress = new ChannelUserAddressEntity();
        $shippingAddress->setId($shippingId);

        $otherAddress = new ChannelUserAddressEntity();
        $otherAddress->setId($otherId);

        /** @var ChannelEntityLoadedEvent<UserAddressEntity|PartialEntity> $event */
        $event = new ChannelEntityLoadedEvent(
            new UserAddressDefinition(),
            [$billingAddress, $shippingAddress, $otherAddress],
            $context
        );

        $subscriber->channelLoaded($event);

        static::assertTrue($billingAddress->isDefaultBillingAddress());
        static::assertFalse($billingAddress->isDefaultShippingAddress());

        static::assertFalse($shippingAddress->isDefaultBillingAddress());
        static::assertTrue($shippingAddress->isDefaultShippingAddress());

        static::assertFalse($otherAddress->isDefaultBillingAddress());
        static::assertFalse($otherAddress->isDefaultShippingAddress());
    }
}
