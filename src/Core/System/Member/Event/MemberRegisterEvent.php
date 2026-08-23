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
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberRegisterEvent extends Event implements ChannelAware, ContenaChannelEvent, MemberAware, MailAware, FlowEventAware
{
    public const string EVENT_NAME = 'member.register';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly MemberEntity $member
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMember(): MemberEntity
    {
        return $this->member;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(MemberAware::MEMBER, new EntityType(MemberDefinition::class));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->member->getEmail() => $this->member->getName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getMemberId(): string
    {
        return $this->member->getId();
    }
}
