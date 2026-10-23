<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Monolog\Level;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\LogAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class UserLoginEvent extends Event implements ChannelAware, ContenaChannelEvent, UserAware, MailAware, ScalarValuesAware, FlowEventAware, LogAware
{
    final public const EVENT_NAME = 'checkout.user.login';

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly UserEntity $user,
        private readonly string $contextToken
    ) {
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::CONTEXT_TOKEN => $this->contextToken,
        ];
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
        return (new EventDataCollection())
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class))
            ->add(FlowMailVariables::CONTEXT_TOKEN, new ScalarValueType(ScalarValueType::TYPE_STRING), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]);
    }

    public function getLogData(): array
    {
        return [
            'userId' => $this->user->getId(),
            'userNumber' => $this->user->getUserNumber(),
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct(
            [
                $this->user->getEmail() => $this->user->getFirstName() . ' ' . $this->user->getLastName(),
            ]
        );
    }
}
