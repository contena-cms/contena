<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class SetupStagingEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SetupStagingEvent::class => 'removeAllConsents',
        ];
    }

    public function removeAllConsents(SetupStagingEvent $event): void
    {
        $this->connection->executeStatement('DELETE FROM `consent_state`');
        $this->connection->executeStatement('DELETE FROM `consent_log`');

        $event->io->info('All consents have been removed for staging setup.');
    }
}
