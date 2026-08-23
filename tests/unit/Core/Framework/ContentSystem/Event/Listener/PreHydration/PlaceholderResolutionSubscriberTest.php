<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\PlaceholderResolutionSubscriber;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\EventFactory;

/**
 * @internal
 */
#[CoversClass(PlaceholderResolutionSubscriber::class)]
class PlaceholderResolutionSubscriberTest extends TestCase
{
    #[TestDox('replaces placeholders in all elements using specification values')]
    public function testReplacesPlaceholdersInAllElements(): void
    {
        $element1 = ContentElementBuilder::create('text', 'e1')
            ->withProperty('title', '{{blogId}}')
            ->build();

        $element2 = ContentElementBuilder::create('text', 'e2')
            ->withProperty('label', 'ID: {{blogId}}')
            ->build();

        $event = EventFactory::preHydration(
            [$element1, $element2],
            placeholderValues: PlaceholderValues::from(['blogId' => 'abc123']),
        );

        $subscriber = new PlaceholderResolutionSubscriber();
        $subscriber->__invoke($event);

        static::assertSame('abc123', $element1->getProperty('title'));
        static::assertSame('ID: abc123', $element2->getProperty('label'));
    }

    #[TestDox('handles empty elements array without errors')]
    public function testHandlesEmptyElementsArray(): void
    {
        $event = EventFactory::preHydration(
            [],
            [],
            null,
            PlaceholderValues::from(['key' => 'value']),
        );

        $subscriber = new PlaceholderResolutionSubscriber();
        $subscriber->__invoke($event);
        static::assertSame([], $event->elements);
    }
}
