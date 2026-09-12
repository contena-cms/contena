<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\DataScopeType;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maintains the one-to-one identity invariant between tenants and tenant data
 * scopes inside the DAL write transaction.
 *
 * @internal
 */
final class TenantDataScopeSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => ['createScopes', 1000],
            EntityDeleteEvent::class => 'deleteScopes',
        ];
    }

    public function createScopes(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity('tenant') as $command) {
            if (!$command instanceof InsertCommand) {
                continue;
            }

            $id = $command->getPrimaryKey()['id'] ?? null;
            if (!\is_string($id) || \strlen($id) !== 16) {
                continue;
            }

            $this->connection->insert('data_scope', [
                'id' => $id,
                'type' => DataScopeType::Tenant->value,
            ]);
        }
    }

    public function deleteScopes(EntityDeleteEvent $event): void
    {
        $ids = $event->getIds('tenant');
        if ($ids === []) {
            return;
        }

        $event->addSuccess(function () use ($ids): void {
            foreach ($ids as $id) {
                if (!\is_string($id) || !Uuid::isValid($id)) {
                    continue;
                }

                $this->connection->delete('data_scope', [
                    'id' => Uuid::fromHexToBytes($id),
                    'type' => DataScopeType::Tenant->value,
                ]);
            }
        });
    }
}
