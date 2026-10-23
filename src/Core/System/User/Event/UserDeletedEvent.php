<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
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
 * @see \Contena\Tests\Integration\Core\System\User\Subscriber\UserBeforeDeleteSubscriberTest
 */
class UserDeletedEvent extends Event implements ContenaChannelEvent, UserAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'checkout.user.deleted';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    /**
     * @param array<string, mixed> $serializedUser
     */
    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly UserEntity $user,
        private readonly array $serializedUser = []
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUserId(): string
    {
        return $this->user->getId();
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

    public function getChannelId(): ?string
    {
        return $this->channelContext->getChannelId();
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

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('user', new EntityType(UserDefinition::class));
    }

    public function getValues(): array
    {
        return [
            'user' => $this->serializedUser,
        ];
    }
}
