<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberDoubleOptInRegistrationEvent extends Event implements ChannelAware, ContenaChannelEvent, MemberAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const string EVENT_NAME = 'member.double_opt_in_registration';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly MemberEntity $member,
        private readonly ChannelContext $channelContext,
        private readonly string $confirmUrl,
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(MemberAware::MEMBER, new EntityType(MemberDefinition::class))
            ->add('confirmUrl', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->mailRecipientStruct ??= new MailRecipientStruct([
            $this->member->getEmail() => $this->member->getName(),
        ]);
    }

    public function getValues(): array
    {
        return ['confirmUrl' => $this->confirmUrl];
    }

    public function getMember(): MemberEntity
    {
        return $this->member;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getMemberId(): string
    {
        return $this->member->getId();
    }
}
