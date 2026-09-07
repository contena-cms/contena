<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Event;

use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\RenderingSpecification;
use Contena\Core\Test\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentTreePreparationEvent::class)]
class ContentTreePreparationEventTest extends TestCase
{
    #[TestDox('Exposes the constructed forest through tree(), then replaces it via replaceTree()')]
    public function testReplaceTreeReplacesWhatTreeReturns(): void
    {
        $tree = [new StoredElement('root-id', 'section')];
        $event = $this->createEvent($tree);

        static::assertSame($tree, $event->tree());

        $replacement = [new StoredElement('injected-id', 'text')];
        $event->replaceTree($replacement);

        static::assertSame($replacement, $event->tree());
    }

    /**
     * @param list<StoredElement> $tree
     */
    private function createEvent(array $tree): ContentTreePreparationEvent
    {
        return new ContentTreePreparationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null),
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            Generator::generateChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
