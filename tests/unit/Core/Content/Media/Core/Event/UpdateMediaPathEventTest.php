<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Core\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateMediaPathEvent::class)]
class UpdateMediaPathEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $event = new UpdateMediaPathEvent(['foo', 'bar']);

        static::assertSame(['foo', 'bar'], iterator_to_array($event->getIterator()));
    }
}
