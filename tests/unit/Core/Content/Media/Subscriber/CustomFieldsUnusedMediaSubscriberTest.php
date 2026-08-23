<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;

/**
 * @internal
 */
#[CoversClass(CustomFieldsUnusedMediaSubscriber::class)]
class CustomFieldsUnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            CustomFieldsUnusedMediaSubscriber::getSubscribedEvents()
        );
    }
}
