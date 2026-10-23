<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexCreatedEvent::class)]
class ElasticsearchIndexCreatedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexCreatedEvent('index', static::createStub(AbstractElasticsearchDefinition::class));
        static::assertSame('index', $event->getIndexName());
    }
}
