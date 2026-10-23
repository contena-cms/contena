<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Content\Flow\Dispatching\Aware\UserRecoveryAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Webhook\NotHookable;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\Event;

#[NotHookable]
class UserAccountRecoverRequestEvent extends Event implements ChannelContextAware, ContenaChannelEvent, UserAware, MailAware, UserRecoveryAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'user.recovery.request';

    private readonly string $shopName;

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly UserRecoveryEntity $userRecovery,
        private readonly string $resetUrl,
    ) {
        $this->shopName = $channelContext->getChannel()->getTranslation('name');
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::RESET_URL => $this->resetUrl,
            FlowMailVariables::SHOP_NAME => $this->shopName,
        ];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUserRecovery(): UserRecoveryEntity
    {
        return $this->userRecovery;
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
            ->add(UserRecoveryAware::CUSTOMER_RECOVERY, new EntityType(UserRecoveryDefinition::class))
            ->add(UserAware::CUSTOMER, new EntityType(UserDefinition::class))
            ->add(FlowMailVariables::RESET_URL, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(FlowMailVariables::SHOP_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct) {
            $user = $this->userRecovery->getUser();
            \assert($user !== null);

            $this->mailRecipientStruct = new MailRecipientStruct([
                $user->getEmail() => $user->getFirstName() . ' ' . $user->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getShopName(): string
    {
        return $this->shopName;
    }

    public function getUser(): ?UserEntity
    {
        return $this->userRecovery->getUser();
    }

    public function getUserId(): string
    {
        return $this->getUserRecovery()->getUserId();
    }

    public function getUserRecoveryId(): string
    {
        return $this->userRecovery->getId();
    }
}
