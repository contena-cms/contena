<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexerLanguageCriteriaEvent::class)]
class ElasticsearchIndexerLanguageCriteriaEventTest extends TestCase
{
    public function testEvent(): void
    {
        $criteria = new Criteria();
        $context = Context::createDefaultContext();

        $event = new ElasticsearchIndexerLanguageCriteriaEvent($criteria, $context);
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}
