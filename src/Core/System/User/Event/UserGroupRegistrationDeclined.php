<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Event\UserGroupAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

class UserGroupRegistrationDeclined extends Event implements ChannelAware, UserAware, MailAware, UserGroupAware, FlowEventAware
{
    final public const EVENT_NAME = 'user.group.registration.declined';

    /**
     * @internal
     */
    public function __construct(
        private readonly UserEntity $user,
        private readonly UserGroupEntity $userGroup,
        private readonly Context $context,
        private readonly ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class))
            ->add(UserGroupAware::CUSTOMER_GROUP, new EntityType(UserGroupDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct) {
            return $this->mailRecipientStruct;
        }

        return new MailRecipientStruct(
            [
                $this->user->getEmail() => $this->user->getFirstName() . ' ' . $this->user->getLastName(),
            ]
        );
    }

    public function getChannelId(): string
    {
        return $this->user->getChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getUserGroup(): UserGroupEntity
    {
        return $this->userGroup;
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }

    public function getUserGroupId(): string
    {
        return $this->userGroup->getId();
    }
}
