<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Blog;

use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexingFinishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed without alternative
 */
class ElasticsearchOptimizeSwitch implements EventSubscriberInterface
{
    /**
     * @deprecated tag:v6.8.0 - will be removed, this app_config value will be always true
     */
    public const FLAG = 'ELASTIC_OPTIMIZE_FLAG';

    /**
     * @internal
     */
    public function __construct(private readonly AbstractKeyValueStorage $storage)
    {
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed without alternative
     */
    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.8.0.0')) {
            return [];
        }

        return [
            ElasticsearchIndexingFinishedEvent::class => 'onIndexingFinished',
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed without alternative
     */
    public function onIndexingFinished(ElasticsearchIndexingFinishedEvent $event): void
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        $this->storage->set(self::FLAG, true);
    }
}
