<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Subscriber;

use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Contena\Core\Content\Media\Exception\IllegalFileNameException;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\Util\PathHelper;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class MediaCreationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeWrite',
        ];
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        $this->filterFilePath($this->getAffected(MediaThumbnailDefinition::ENTITY_NAME, $event));
        $this->filterFilePath($this->getAffected(MediaFolderDefinition::ENTITY_NAME, $event));
        $this->filterFilePath($this->getAffected(MediaDefinition::ENTITY_NAME, $event));
    }

    /**
     * @param array<WriteCommand> $commands
     */
    private function filterFilePath(array $commands): void
    {
        foreach ($commands as $command) {
            // Remove control characters and invisible formatting characters
            try {
                $path = PathHelper::stripControlAndFormatChars($command->getPayload()['path']);
            } catch (IllegalFileNameException) {
                $path = null;
            }

            $command->addPayload('path', $path);
        }
    }

    /**
     * @return array<WriteCommand>
     */
    private function getAffected(string $entityName, EntityWriteEvent $event): array
    {
        return array_filter($event->getCommandsForEntity($entityName), static function (WriteCommand $command) {
            if ($command instanceof DeleteCommand) {
                return false;
            }

            if ($command->hasField('path') && $command->getPayload()['path'] !== null) {
                return true;
            }

            return false;
        });
    }
}
