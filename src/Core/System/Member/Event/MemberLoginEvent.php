<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Monolog\Level;
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
use Contena\Core\Framework\Log\LogAware;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberLoginEvent extends Event implements ChannelAware, ContenaChannelEvent, MemberAware, MailAware, ScalarValuesAware, FlowEventAware, LogAware
{
    final public const string EVENT_NAME = 'member.login';

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly MemberEntity $member,
        private readonly string $contextToken,
    ) {
    }

    public function getValues(): array
    {
        return ['contextToken' => $this->contextToken];
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

    public function getContextToken(): string
    {
        return $this->contextToken;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(MemberAware::MEMBER, new EntityType(MemberDefinition::class))
            ->add('contextToken', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getLogData(): array
    {
        return [
            'memberId' => $this->member->getId(),
            'memberNumber' => $this->member->getMemberNumber(),
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }

    public function getMemberId(): string
    {
        return $this->member->getId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->member->getEmail() => $this->member->getName(),
        ]);
    }
}
