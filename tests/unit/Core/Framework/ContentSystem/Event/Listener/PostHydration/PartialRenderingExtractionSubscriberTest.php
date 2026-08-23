<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Event\Listener\PostHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Event\Listener\PostHydration\PartialRenderingExtractionSubscriber;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Contena\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Contena\Core\Framework\ContentSystem\Output\PartialRenderer;
use Contena\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\EventFactory;

/**
 * @internal
 */
#[CoversClass(PartialRenderingExtractionSubscriber::class)]
class PartialRenderingExtractionSubscriberTest extends TestCase
{
    private PartialRenderingExtractionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new PartialRenderingExtractionSubscriber(
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor())
        );
    }

    #[TestDox('extracts target subtree when target element ID is set')]
    public function testExtractsTargetSubtreeWhenTargetElementIdIsSet(): void
    {
        $target = ContentElementBuilder::create('text', 'target-id')->build();
        $sibling = ContentElementBuilder::create('text', 'sibling-id')->build();
        $root = ContentElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target, $sibling])
            ->build();

        $event = EventFactory::postHydration([$root], targetElementId: 'target-id');
        $this->subscriber->__invoke($event);

        static::assertCount(1, $event->elements);
        static::assertSame('target-id', $event->elements[0]->getId());
    }

    #[TestWithJson('[null]')]
    #[TestWithJson('[""]')]
    #[TestDox('skips extraction when target element ID is $targetElementId')]
    public function testSkipsExtractionWhenTargetElementIdIsNotSet(?string $targetElementId): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::postHydration([$element], targetElementId: $targetElementId);
        $this->subscriber->__invoke($event);

        static::assertSame([$element], $event->elements);
    }
}
