<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class UserSetDefaultBillingAddressEvent extends Event implements ChannelAware, ContenaChannelEvent, FlowEventAware, UserAware
{
    final public const EVENT_NAME = 'checkout.user.default.billing.address.event';

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly UserEntity $user,
        private string $addressId
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class));
    }

    public function getAddressId(): string
    {
        return $this->addressId;
    }

    public function setAddressId(string $addressId): void
    {
        $this->addressId = $addressId;
    }
}
