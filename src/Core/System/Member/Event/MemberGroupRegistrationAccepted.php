<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Event\MemberGroupAware;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberGroupRegistrationAccepted extends Event implements ChannelAware, MemberAware, MailAware, MemberGroupAware, FlowEventAware
{
    final public const EVENT_NAME = 'member.group.registration.accepted';

    /**
     * @internal
     */
    public function __construct(
        private readonly MemberEntity $member,
        private readonly MemberGroupEntity $memberGroup,
        private readonly Context $context,
        private readonly ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(MemberAware::MEMBER, new EntityType(MemberDefinition::class))
            ->add(MemberGroupAware::MEMBER_GROUP, new EntityType(MemberGroupDefinition::class));
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
                $this->member->getEmail() => $this->member->getName(),
            ]
        );
    }

    public function getChannelId(): string
    {
        return $this->member->getChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMember(): MemberEntity
    {
        return $this->member;
    }

    public function getMemberGroup(): MemberGroupEntity
    {
        return $this->memberGroup;
    }

    public function getMemberId(): string
    {
        return $this->member->getId();
    }

    public function getMemberGroupId(): string
    {
        return $this->memberGroup->getId();
    }
}
