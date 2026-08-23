<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\DataAbstractionLayer;

use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LandingPageIndexer extends EntityIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<LandingPageCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'landing_page.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new LandingPageIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(LandingPageDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        return new LandingPageIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_unique(array_filter($ids));
        if ($ids === []) {
            return;
        }

        // This indexer is only used to update the seo urls over the SeoUrlUpdater, so we only dispatch the event here
        $this->eventDispatcher->dispatch(new LandingPageIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getOptions(): array
    {
        return [
            'landing_page.seo-url',
        ];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
