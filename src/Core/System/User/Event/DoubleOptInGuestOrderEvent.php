<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\Event;

class DoubleOptInGuestOrderEvent extends Event implements ChannelContextAware, UserAware, MailAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'checkout.user.double_opt_in_guest_order';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly UserEntity $user,
        private readonly ChannelContext $channelContext,
        private readonly string $confirmUrl,
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class))
            ->add(FlowMailVariables::CONFIRM_URL, new ScalarValueType(ScalarValueType::TYPE_STRING), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]);
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::CONFIRM_URL => $this->confirmUrl];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->user->getEmail() => $this->user->getName() . ' ' . $this->user->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }
}
