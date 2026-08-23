<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdateEvent::class)]
class SeoUrlUpdateEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new SeoUrlUpdateEvent([], $context);

        static::assertSame($context, $event->getContext());
    }
}
