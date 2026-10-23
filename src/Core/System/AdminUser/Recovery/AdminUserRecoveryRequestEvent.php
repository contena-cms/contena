<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Recovery;

use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\AdminUserAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Webhook\NotHookable;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Symfony\Contracts\EventDispatcher\Event;

#[NotHookable]
class AdminUserRecoveryRequestEvent extends Event implements AdminUserAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'admin_user.recovery.request';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly AdminUserRecoveryEntity $admin_userRecovery,
        private readonly string $resetUrl,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUserRecovery(): AdminUserRecoveryEntity
    {
        return $this->admin_userRecovery;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(AdminUserAware::USER_RECOVERY, new EntityType(AdminUserRecoveryDefinition::class))
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
            /** @var AdminUserEntity $admin_user */
            $admin_user = $this->admin_userRecovery->getUser();

            $this->mailRecipientStruct = new MailRecipientStruct([
                $admin_user->getEmail() => $admin_user->getFirstName() . ' ' . $admin_user->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getChannelId(): ?string
    {
        return null;
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getUserId(): string
    {
        return $this->admin_userRecovery->getId();
    }
}
