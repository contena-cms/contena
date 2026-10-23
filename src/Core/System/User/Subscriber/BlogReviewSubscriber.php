<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\System\User\Service\BlogReviewCountService;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class BlogReviewSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly BlogReviewCountService $blogReviewCountService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'blog_review.written' => 'createReview',
            EntityDeleteEvent::class => 'detectChangeset',
            'blog_review.deleted' => 'onReviewDeleted',
        ];
    }

    public function detectChangeset(EntityDeleteEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof DeleteCommand) {
                continue;
            }

            if ($command->getEntityName() !== BlogReviewDefinition::ENTITY_NAME) {
                continue;
            }

            $command->requestChangeSet();
        }
    }

    public function onReviewDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            if ($result->getEntityName() !== BlogReviewDefinition::ENTITY_NAME) {
                continue;
            }

            $changeset = $result->getChangeSet();
            \assert($changeset !== null);

            $id = $changeset->getBefore('user_id');

            if (!\is_string($id)) {
                continue;
            }

            $this->blogReviewCountService->updateReviewCountForUser($id);
        }
    }

    public function createReview(EntityWrittenEvent $reviewEvent): void
    {
        if ($reviewEvent->getEntityName() !== BlogReviewDefinition::ENTITY_NAME) {
            return;
        }

        $ids = $reviewEvent->getIds();

        $this->blogReviewCountService->updateReviewCount($ids);
    }
}
