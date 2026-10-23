<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Content\Flow\SendMailActionTest
 */
class UserRegisterEvent extends Event implements ChannelAware, ContenaChannelEvent, UserAware, MailAware, FlowEventAware
{
    public const EVENT_NAME = 'checkout.user.register';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly UserEntity $user
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
        return (new EventDataCollection())
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->user->getEmail() => $this->user->getFirstName() . ' ' . $this->user->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }
}
