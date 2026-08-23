<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
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

class MemberPasswordChangedEvent extends Event implements ChannelAware, ContenaChannelEvent, MemberAware, MailAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'member.password.changed';

    private readonly string $channelName;

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly MemberEntity $member,
    ) {
        $this->channelName = $channelContext->getChannel()->getTranslation('name');
    }

    public function getMemberId(): string
    {
        return $this->member->getId();
    }

    public function getMember(): MemberEntity
    {
        return $this->member;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(MemberAware::MEMBER, new EntityType(MemberDefinition::class))
            ->add(FlowMailVariables::CHANNEL_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->member->getEmail() => $this->member->getName(),
        ]);
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::CHANNEL_NAME => $this->channelName,
        ];
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }
}
