<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Framework;

use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class SystemUpdateListener
{
    public const CONFIG_KEY = 'elasticsearch.indexing.entities';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractKeyValueStorage $storage,
        private readonly ElasticsearchIndexer $indexer,
        private readonly MessageBusInterface $messageBus,
        private readonly IndexMappingUpdater $mappingUpdater
    ) {
    }

    public function __invoke(UpdatePostFinishEvent $event): void
    {
        $this->mappingUpdater->update($event->getContext());

        $entitiesToReindex = $this->storage->get(self::CONFIG_KEY, []);

        if ($entitiesToReindex === []) {
            return;
        }

        $messagesToDispatch = [];
        $offset = null;
        while ($message = $this->indexer->iterate($offset)) {
            $offset = $message->getOffset();

            $messagesToDispatch[] = $message;
        }

        $lastMessage = end($messagesToDispatch);

        if (!$lastMessage instanceof ElasticsearchIndexingMessage) {
            return;
        }

        $lastMessage->markAsLastMessage();

        foreach ($messagesToDispatch as $message) {
            $this->messageBus->dispatch($message);
        }

        $this->storage->remove(self::CONFIG_KEY);
    }
}
