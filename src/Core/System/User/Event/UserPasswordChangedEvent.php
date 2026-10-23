<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

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
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class UserPasswordChangedEvent extends Event implements ChannelAware, ContenaChannelEvent, UserAware, MailAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'user.password.changed';

    private readonly string $shopName;

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly UserEntity $user,
    ) {
        $this->shopName = $channelContext->getChannel()->getTranslation('name');
    }

    public function getUserId(): string
    {
        return $this->user->getId();
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class))
            ->add(FlowMailVariables::SHOP_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->user->getEmail() => $this->user->getFirstName() . ' ' . $this->user->getLastName(),
        ]);
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::SHOP_NAME => $this->shopName,
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

    public function getShopName(): string
    {
        return $this->shopName;
    }
}
