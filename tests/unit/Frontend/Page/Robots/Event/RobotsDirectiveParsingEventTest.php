<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Robots\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Frontend\Page\Robots\Event\RobotsDirectiveParsingEvent;
use Contena\Frontend\Page\Robots\Parser\ParsedRobots;

/**
 * @internal
 */
#[CoversClass(RobotsDirectiveParsingEvent::class)]
class RobotsDirectiveParsingEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $text = "User-agent: *\nDisallow: /admin/";
        $parsedResult = new ParsedRobots([], []);
        $context = Context::createDefaultContext();
        $channelId = 'test-channel-id';

        $event = new RobotsDirectiveParsingEvent($text, $parsedResult, $context, $channelId);

        static::assertSame($text, $event->text);
        static::assertSame($parsedResult, $event->parsedResult);
        static::assertSame($context, $event->getContext());
        static::assertSame($channelId, $event->channelId);
    }

    public function testSetParsedResultUpdatesResult(): void
    {
        $originalResult = new ParsedRobots([], []);
        $newResult = new ParsedRobots([], [], []);
        $context = Context::createDefaultContext();

        $event = new RobotsDirectiveParsingEvent('test', $originalResult, $context);

        static::assertSame($originalResult, $event->parsedResult);

        $event->parsedResult = $newResult;

        static::assertSame($newResult, $event->parsedResult);
        static::assertNotSame($originalResult, $event->parsedResult);
    }

    public function testChannelIdCanBeNull(): void
    {
        $parsedResult = new ParsedRobots([], []);
        $context = Context::createDefaultContext();

        $event = new RobotsDirectiveParsingEvent('test', $parsedResult, $context, null);

        static::assertNull($event->channelId);
    }
}
