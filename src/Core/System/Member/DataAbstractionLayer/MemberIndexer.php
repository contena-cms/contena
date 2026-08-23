<?php declare(strict_types=1);

namespace Contena\Core\System\Member\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Member\Event\MemberIndexerEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MemberIndexer extends EntityIndexer
{
    final public const MANY_TO_MANY_ID_FIELD_UPDATER = 'member.many-to-many-id-field';
    private const PRIMARY_KEYS_WITH_PROPERTY_CHANGE = ['email', 'name', 'phoneNumber'];

    /**
     * @internal
     *
     * @param EntityRepository<MemberCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'member.indexer';
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new MemberIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(MemberDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        $indexing = new MemberIndexingMessage(array_values($updates), null, $event->getContext());

        if ($getIdsWithProfileChange = $event->getPrimaryKeysWithPropertyChange(MemberDefinition::ENTITY_NAME, self::PRIMARY_KEYS_WITH_PROPERTY_CHANGE)) {
            $indexing->setIds($getIdsWithProfileChange);
        }

        return $indexing;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids)) {
            return;
        }

        $ids = array_unique(array_filter($ids));
        if ($ids === [] || !$message instanceof MemberIndexingMessage) {
            return;
        }

        $context = $message->getContext();

        if ($message->allow(self::MANY_TO_MANY_ID_FIELD_UPDATER)) {
            $this->manyToManyIdFieldUpdater->update(MemberDefinition::ENTITY_NAME, $ids, $context);
        }

        $this->eventDispatcher->dispatch(new MemberIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getOptions(): array
    {
        return [self::MANY_TO_MANY_ID_FIELD_UPDATER];
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
