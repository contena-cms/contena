<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Core\Event;

use Contena\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UpdateThumbnailPathEvent::class)]
class UpdateThumbnailPathEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $event = new UpdateThumbnailPathEvent(['foo', 'bar']);

        static::assertSame(['foo', 'bar'], iterator_to_array($event->getIterator()));
    }
}
