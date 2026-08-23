<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Event\BusinessEventCollectorResponse;
use Contena\Core\Framework\Event\BusinessEventDefinition;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Resource\BusinessEventsResource;

/**
 * @internal
 */
#[CoversClass(BusinessEventsResource::class)]
class BusinessEventsResourceTest extends TestCase
{
    public function testReturnsEventsAsResource(): void
    {
        $definition = new BusinessEventDefinition('test.event', TestResourceEventClass::class, ['memberId' => 'string']);
        $response = new BusinessEventCollectorResponse([$definition]);

        $collector = static::createStub(BusinessEventCollector::class);
        $collector->method('collect')->willReturn($response);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $resource = new BusinessEventsResource($collector, $contextProvider);
        $result = ($resource)();

        static::assertSame('contena://business-events', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $events = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $events);
        static::assertSame('test.event', $events[0]['name']);
        static::assertSame(TestResourceEventClass::class, $events[0]['class']);
        static::assertSame(['memberId' => 'string'], $events[0]['data']);
    }
}

/**
 * @internal
 */
class TestResourceEventClass
{
}
