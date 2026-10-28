<?php declare(strict_types=1);

namespace Contena\Core\System\User\Recovery;

use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\NotHookable;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('fundamentals@framework')]
#[NotHookable]
class UserRecoveryRequestEvent extends Event implements UserAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'user.recovery.request';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly UserRecoveryEntity $userRecovery,
        private readonly string $resetUrl,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUserRecovery(): UserRecoveryEntity
    {
        return $this->userRecovery;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(UserAware::USER_RECOVERY, new EntityType(UserRecoveryDefinition::class))
            ->add(FlowMailVariables::RESET_URL, new ScalarValueType('string'))
        ;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::RESET_URL => $this->resetUrl,
        ];
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            /** @var UserEntity $user */
            $user = $this->userRecovery->getUser();

            $this->mailRecipientStruct = new MailRecipientStruct([
                $user->getEmail() => $user->getFirstName() . ' ' . $user->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getUserId(): string
    {
        return $this->userRecovery->getId();
    }
}
