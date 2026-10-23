<?php declare(strict_types=1);

namespace Contena\Core\System\User\DataAbstractionLayer;

use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\Event\UserIndexerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserIndexer extends EntityIndexer
{
    final public const MANY_TO_MANY_ID_FIELD_UPDATER = 'user.many-to-many-id-field';
    final public const NEWSLETTER_CHANNELS_UPDATER = 'user.newsletter-sales-channels';

    private const PRIMARY_KEYS_WITH_PROPERTY_CHANGE = ['email', 'firstName', 'lastName'];

    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $repository
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly ManyToManyIdFieldUpdater $manyToManyIdFieldUpdater,
        private readonly UserNewsletterChannelsUpdater $userNewsletterChannelsUpdater,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'user.indexer';
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

        return new UserIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(UserDefinition::ENTITY_NAME);

        if ($updates === []) {
            return null;
        }

        $indexing = new UserIndexingMessage(array_values($updates), null, $event->getContext());

        if ($getIdsWithProfileChange = $event->getPrimaryKeysWithPropertyChange(UserDefinition::ENTITY_NAME, self::PRIMARY_KEYS_WITH_PROPERTY_CHANGE)) {
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
        if ($ids === [] || !$message instanceof UserIndexingMessage) {
            return;
        }

        $context = $message->getContext();

        if ($message->getIds() !== []) {
            $this->userNewsletterChannelsUpdater->updateUsersRecipient($message->getIds());
        }

        if ($message->allow(self::MANY_TO_MANY_ID_FIELD_UPDATER)) {
            $this->manyToManyIdFieldUpdater->update(UserDefinition::ENTITY_NAME, $ids, $context);
        }

        if ($message->allow(self::NEWSLETTER_CHANNELS_UPDATER)) {
            $this->userNewsletterChannelsUpdater->update($ids, true);
        }

        $this->eventDispatcher->dispatch(new UserIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getOptions(): array
    {
        return [
            self::MANY_TO_MANY_ID_FIELD_UPDATER,
            self::NEWSLETTER_CHANNELS_UPDATER,
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
