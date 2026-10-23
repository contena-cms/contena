<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Framework;

use Contena\Core\Framework\Event\SystemInstallCompletedEvent;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 */
#[AsEventListener]
class SystemInstallListener
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
    ) {
    }

    public function __invoke(SystemInstallCompletedEvent $event): void
    {
        try {
            $this->indexer->createIndices();
        } catch (\Throwable) {
            // Unreachable or misconfigured Elasticsearch must not fail system:install
        }
    }
}
