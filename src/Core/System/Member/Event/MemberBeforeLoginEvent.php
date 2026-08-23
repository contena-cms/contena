<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class MemberBeforeLoginEvent extends Event implements ChannelAware, ContenaChannelEvent, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const string EVENT_NAME = 'member.before.login';

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly string $email,
    ) {
    }

    public function getValues(): array
    {
        return ['email' => $this->email];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getEmail(): string
    {
        return $this->email;
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
        return new EventDataCollection()
            ->add('email', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }
}
