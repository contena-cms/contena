<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\Event\ChannelIndexerEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ChannelIndexer extends EntityIndexer
{
    final public const string MANY_TO_MANY_UPDATER = 'channel.many-to-many';

    /**
     * @internal
     *
     * @param EntityRepository<ChannelCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ManyToManyIdFieldUpdater $manyToManyUpdater
    ) {
    }

    public function getName(): string
    {
        return 'channel.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new ChannelIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(ChannelDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        return new ChannelIndexingMessage(array_values($updates), null, $event->getContext());
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

        if ($message->allow(self::MANY_TO_MANY_UPDATER)) {
            $this->manyToManyUpdater->update(ChannelDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        $this->eventDispatcher->dispatch(new ChannelIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    public function getOptions(): array
    {
        return [self::MANY_TO_MANY_UPDATER];
    }
}
