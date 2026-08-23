<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Flow\Api\FlowActionCollectorResponse;
use Contena\Core\Content\Flow\Api\FlowActionDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Resource\FlowActionsResource;

/**
 * @internal
 */
#[CoversClass(FlowActionsResource::class)]
class FlowActionsResourceTest extends TestCase
{
    public function testReturnsSortedActionsAsResource(): void
    {
        $context = Context::createDefaultContext();

        $action1 = new FlowActionDefinition('action.send-mail', ['member'], true);
        $action2 = new FlowActionDefinition('action.add-tag', ['entity'], false);
        $response = new FlowActionCollectorResponse([$action1, $action2]);

        $collector = static::createStub(FlowActionCollector::class);
        $collector->method('collect')->willReturn($response);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $resource = new FlowActionsResource($collector, $contextProvider);
        $result = ($resource)();

        static::assertSame('contena://flow-actions', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $actions = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $actions);
        static::assertSame('action.add-tag', $actions[0]['name']);
        static::assertSame(['entity'], $actions[0]['requirements']);
        static::assertFalse($actions[0]['delayable']);
        static::assertSame('action.send-mail', $actions[1]['name']);
        static::assertSame(['member'], $actions[1]['requirements']);
        static::assertTrue($actions[1]['delayable']);
    }
}
