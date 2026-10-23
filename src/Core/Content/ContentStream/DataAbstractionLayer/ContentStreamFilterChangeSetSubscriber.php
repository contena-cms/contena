<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\DataAbstractionLayer;

use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A blog stream filter write does not reliably expose the stream it belongs to: a delete carries
 * only the primary key, a partial update only the changed fields, and a reassignment carries the new
 * stream while the previous one is only known from the row on disk. Requesting a change set puts that
 * previous row state on the written event, so every affected stream can be resolved for re-indexing.
 *
 * @internal
 */
final class ContentStreamFilterChangeSetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'triggerChangeSet',
        ];
    }

    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== ContentStreamFilterDefinition::ENTITY_NAME) {
                continue;
            }

            // DeleteCommand and UpdateCommand are the only change-set aware commands; inserts have
            // no previous state to read, and their payload always carries the stream id anyway
            if (!$command instanceof ChangeSetAware) {
                continue;
            }

            $command->requestChangeSet();
        }
    }
}
