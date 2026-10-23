<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexAliasSwitchedEvent;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexAliasSwitchedEvent::class)]
class ElasticsearchIndexAliasSwitchedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexAliasSwitchedEvent(['alias' => 'index']);
        static::assertSame(['alias' => 'index'], $event->getChanges());
    }
}
