<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UserCredentialsChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserEvents::USER_WRITTEN_EVENT => 'onUserWritten',
            UserEvents::USER_DELETED_EVENT => 'onUserDeleted',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if ($this->userCredentialsChanged($payload)) {
                $this->refreshTokenRepository->revokeRefreshTokensForUser($payload['id']);
                $this->updateLastUpdatedPasswordTimestamp($payload['id']);
            }
        }
    }

    public function onUserDeleted(EntityDeletedEvent $event): void
    {
        $ids = $event->getIds();

        foreach ($ids as $id) {
            $this->refreshTokenRepository->revokeRefreshTokensForUser($id);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function userCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']) || (\array_key_exists('active', $payload) && $payload['active'] === false);
    }

    private function updateLastUpdatedPasswordTimestamp(string $userId): void
    {
        $this->connection->update('user', [
            'last_updated_password_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => Uuid::fromHexToBytes($userId),
        ]);
    }
}
