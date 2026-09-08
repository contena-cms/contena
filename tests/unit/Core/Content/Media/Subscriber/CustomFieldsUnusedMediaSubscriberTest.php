<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Subscriber;

use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
