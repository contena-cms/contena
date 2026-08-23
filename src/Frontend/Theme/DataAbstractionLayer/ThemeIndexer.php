<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Event\ThemeIndexerEvent;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ThemeIndexer extends EntityIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'theme.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->getIterator($offset);
        $ids = $iterator->fetch();

        if ($ids === []) {
            return null;
        }

        return new ThemeIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeysWithPropertyChange(ThemeDefinition::ENTITY_NAME, ['parentThemeId']);

        if ($updates === []) {
            return null;
        }

        return new ThemeIndexingMessage(array_values($updates), null, $event->getContext());
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

        $context = $message->getContext();

        RetryableTransaction::retryable($this->connection, function () use ($ids): void {
            $this->connection->executeStatement(
                'DELETE FROM theme_child WHERE parent_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY]
            );

            $this->connection->executeStatement(
                'INSERT IGNORE INTO theme_child (child_id, parent_id)
                    (
                        SELECT id as child_id, parent_theme_id as parent_id FROM theme
                        WHERE parent_theme_id IS NOT NULL AND id IN (:ids) AND technical_name IS NULL
                    )
                ',
                ['ids' => Uuid::fromHexToBytesList($ids)],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        $this->eventDispatcher->dispatch(new ThemeIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->getIterator(null)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array{offset: int|null}|null $offset
     */
    private function getIterator(?array $offset): IterableQuery
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);
    }
}
