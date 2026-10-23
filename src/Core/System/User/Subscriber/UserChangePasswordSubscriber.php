<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\System\User\UserEvents;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UserChangePasswordSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserEvents::CUSTOMER_WRITTEN_EVENT => 'onUserWritten',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getPayloads() as $payload) {
            $password = $payload['password'] ?? null;
            if ($password !== null && $password !== '') {
                $this->clearLegacyPassword($payload['id']);
            }
        }
    }

    private function clearLegacyPassword(string $userId): void
    {
        $this->connection->executeStatement(
            'UPDATE `user` SET `legacy_password` = null, `legacy_encoder` = null WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($userId),
            ]
        );
    }
}
