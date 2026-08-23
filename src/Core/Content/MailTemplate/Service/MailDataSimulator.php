<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\UserEntity;

/**
 * Builds deterministic, non-persisted sample data for mail template previews.
 *
 * @internal
 */
class MailDataSimulator
{
    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(string $eventName, Context $context): array
    {
        if ($eventName !== UserRecoveryRequestEvent::EVENT_NAME) {
            return [];
        }

        $userId = Uuid::randomHex();
        $user = new UserEntity()->assign([
            'id' => $userId,
            'localeId' => $context->getLanguageId(),
            'username' => 'admin',
            'name' => 'Contena Administrator',
            'email' => 'admin@example.com',
            'stateId' => Uuid::randomHex(),
            'admin' => true,
            'timeZone' => 'UTC',
        ]);

        $userRecovery = new UserRecoveryEntity()->assign([
            'id' => Uuid::randomHex(),
            'userId' => $userId,
            'hash' => 'preview-recovery-hash',
            'user' => $user,
        ]);

        return [
            'userRecovery' => $userRecovery,
            'resetUrl' => 'https://example.com/admin/recovery/preview-recovery-hash',
        ];
    }
}
