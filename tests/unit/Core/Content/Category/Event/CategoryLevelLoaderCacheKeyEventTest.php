<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(CategoryLevelLoaderCacheKeyEvent::class)]
class CategoryLevelLoaderCacheKeyEventTest extends TestCase
{
    public function testEvent(): void
    {
        $defaultContext = Context::createDefaultContext();
        // Prepare test data
        $initialParts = ['part1' => 'test', 'part2' => 'test2'];
        $rootId = 'root-id';
        $depth = 3;
        $channelId = 'sales-channel-id';
        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn($channelId);
        $context->method('getContext')->willReturn($defaultContext);

        $criteria = new Criteria();

        $event = new CategoryLevelLoaderCacheKeyEvent(
            $initialParts,
            $rootId,
            $depth,
            $context,
            $criteria
        );

        static::assertSame($initialParts, $event->getParts());
        static::assertSame($context, $event->getChannelContext());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertTrue($event->shouldCache());

        $newParts = ['new-part1' => 'test', 'new-part2' => 'test2'];
        $event->setParts($newParts);
        static::assertSame($newParts, $event->getParts());

        $event->addPart('new-part3', 'test3');
        static::assertSame(array_merge($newParts, ['new-part3' => 'test3']), $event->getParts());

        $event->removePart('new-part3');
        static::assertSame($newParts, $event->getParts());

        $event->disableCaching();
        static::assertFalse($event->shouldCache());
    }
}
