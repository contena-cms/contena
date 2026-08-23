<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ObjectType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberDeletedEvent extends Event implements ContenaChannelEvent, MemberAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const string EVENT_NAME = 'member.deleted';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    /**
     * @param array<string, mixed> $serializedMember
     */
    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly MemberEntity $member,
        private readonly array $serializedMember = []
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMemberId(): string
    {
        return $this->member->getId();
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

    public function getChannelId(): ?string
    {
        return $this->channelContext->getChannelId();
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

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add('member', new ObjectType());
    }

    public function getValues(): array
    {
        return [
            'member' => $this->serializedMember,
        ];
    }
}
