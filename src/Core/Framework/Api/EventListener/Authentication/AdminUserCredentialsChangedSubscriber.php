<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\OAuth\AuthCodeRepository;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AdminUserCredentialsChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepository $authCodeRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdminUserEvents::USER_WRITTEN_EVENT => 'onUserWritten',
            AdminUserEvents::USER_DELETED_EVENT => 'onUserDeleted',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if ($this->adminUserCredentialsChanged($payload)) {
                $this->revokeUserTokens($payload['id']);
                $this->updateLastUpdatedPasswordTimestamp($payload['id']);
            }
        }
    }

    public function onUserDeleted(EntityDeletedEvent $event): void
    {
        $ids = $event->getIds();

        foreach ($ids as $id) {
            $this->revokeUserTokens($id);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function adminUserCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']) || (\array_key_exists('active', $payload) && $payload['active'] === false);
    }

    private function updateLastUpdatedPasswordTimestamp(string $adminUserId): void
    {
        $this->connection->update('admin_user', [
            'last_updated_password_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => Uuid::fromHexToBytes($adminUserId),
        ]);
    }

    private function revokeUserTokens(string $adminUserId): void
    {
        $this->refreshTokenRepository->revokeRefreshTokensForUser($adminUserId);
        $this->authCodeRepository->revokeAuthCodesForUser($adminUserId);
    }
}
