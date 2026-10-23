<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Subscriber;

use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Derives the read-only `descriptionTeaser` from the `description` on write via the shared
 * {@see BlogDescriptionTeaserBuilder}, keeping the teaser cheap to load in listings without
 * stripping HTML on every read. Existing blogs are backfilled asynchronously by the
 * `blog.description_teaser.indexer` scheduled in the migration that adds the column.
 *
 * @internal
 */
class BlogDescriptionTeaserSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly BlogDescriptionTeaserBuilder $teaserBuilder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeWrite',
        ];
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        $commands = $event->getCommandsForEntity(BlogTranslationDefinition::ENTITY_NAME);

        foreach ($commands as $command) {
            if (!$command->hasField('description')) {
                continue;
            }

            $command->addPayload('description_teaser', $this->teaserBuilder->build($command->getPayload()['description'] ?? null));
        }
    }
}
