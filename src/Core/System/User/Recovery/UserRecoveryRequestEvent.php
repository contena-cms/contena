<?php declare(strict_types=1);

namespace Contena\Core\System\User\Recovery;

use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Symfony\Contracts\EventDispatcher\Event;

class UserRecoveryRequestEvent extends Event implements FlowEventAware, UserAware, MailAware, ScalarValuesAware
{
    final public const string EVENT_NAME = 'user.recovery.request';

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

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getUserId(): string
    {
        return $this->userRecovery->getUserId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(UserAware::USER_RECOVERY, new EntityType(UserRecoveryDefinition::class))
            ->add('resetUrl', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        $user = $this->userRecovery->getUser();
        if ($user === null) {
            return new MailRecipientStruct([]);
        }

        return new MailRecipientStruct([$user->getEmail() => $user->getName()]);
    }

    public function getValues(): array
    {
        return ['resetUrl' => $this->resetUrl];
    }
}
