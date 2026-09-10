<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Event;

use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Contena\Core\Framework\ContentSystem\RenderingSpecification;
use Contena\Core\Test\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RenderedTreeFinalizationEvent::class)]
class RenderedTreeFinalizationEventTest extends TestCase
{
    #[TestDox('Exposes the constructed forest through tree(), then replaces it via replaceTree()')]
    public function testReplaceTreeReplacesWhatTreeReturns(): void
    {
        $tree = [new RenderedElement('root-id', 'section')];
        $event = $this->createEvent($tree);

        static::assertSame($tree, $event->tree());

        $replacement = [new RenderedElement('injected-id', 'text')];
        $event->replaceTree($replacement);

        static::assertSame($replacement, $event->tree());
    }

    /**
     * @param array<array-key, mixed> $replacement
     */
    #[DataProvider('foreignForestProvider')]
    public function testReplaceTreeRefusesAForeignForest(array $replacement, ContentSystemException $expected): void
    {
        $event = $this->createEvent([new RenderedElement('root-id', 'section')]);
        $this->expectExceptionObject($expected);
        $event->replaceTree($replacement); // @phpstan-ignore argument.type
    }

    /**
     * @param array<array-key, mixed> $replacement
     */
    #[DataProvider('foreignForestProvider')]
    public function testConstructorRefusesAForeignForest(array $replacement, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);
        $this->createEvent($replacement); // @phpstan-ignore argument.type
    }

    public function testRefusedReplacementLeavesTheForestInPlace(): void
    {
        $tree = [new RenderedElement('root-id', 'section')];
        $event = $this->createEvent($tree);

        try {
            $event->replaceTree([new StoredElement('stored-id', 'text')]); // @phpstan-ignore argument.type
        } catch (ContentSystemException) {
        }

        static::assertSame($tree, $event->tree());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, ContentSystemException}>
     */
    public static function foreignForestProvider(): iterable
    {
        yield 'a stored element' => [[new StoredElement('stored-id', 'text')], ContentSystemException::invalidMapValue('Rendered content tree', '0', RenderedElement::class, StoredElement::class)];
        yield 'a stored element behind a valid one' => [[new RenderedElement('root-id', 'section'), new StoredElement('stored-id', 'text')], ContentSystemException::invalidMapValue('Rendered content tree', '1', RenderedElement::class, StoredElement::class)];
        yield 'an element still in array form' => [[['id' => 'root-id', 'component' => 'section']], ContentSystemException::invalidMapValue('Rendered content tree', '0', RenderedElement::class, 'array')];
        yield 'a forest keyed by element id' => [['root-id' => new RenderedElement('root-id', 'section')], ContentSystemException::invalidMapValue('Rendered content tree', 'tree', 'list<RenderedElement>', 'array with non-list keys')];
        yield 'a forest with a gap left by an unset root' => [[0 => new RenderedElement('first-id', 'section'), 2 => new RenderedElement('third-id', 'section')], ContentSystemException::invalidMapValue('Rendered content tree', 'tree', 'list<RenderedElement>', 'array with non-list keys')];
    }

    /**
     * @param list<RenderedElement> $tree
     */
    private function createEvent(array $tree): RenderedTreeFinalizationEvent
    {
        return new RenderedTreeFinalizationEvent(
            $tree,
            LayoutReference::create('layout-1', 'Test', null),
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            Generator::generateChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
