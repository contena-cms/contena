<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\Op\AttachElements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttachElements::class)]
class AttachElementsTest extends TestCase
{
    #[TestDox('attaches every supplied element in order, aggregating affected and created across them')]
    public function testAttachesEveryElementInOrder(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'Ct:Block')]);
        $elements = [
            new StoredElement('first', 'Ct:Card', [], [], [
                'content' => [new StoredElement('child', 'Ct:Card')],
            ]),
            new StoredElement('second', 'Ct:Block'),
        ];

        $op = $this->op($elements);
        $result = $op->apply($tree);

        static::assertSame(
            ['Ct:Block', 'Ct:Card', 'Ct:Block'],
            array_map(static fn (StoredElement $root): string => $root->component, $result->roots),
        );
        static::assertSame('existing', $result->roots[0]->id);

        $first = $result->roots[1];
        $second = $result->roots[2];
        $expected = [$first->id, $first->slots['content'][0]->id, $second->id];
        static::assertSame($expected, $op->affected());
        static::assertSame($expected, $op->created());
    }

    #[TestDox('inserts every element at an explicit index, preserving their order')]
    public function testInsertsEveryElementAtIndexPreservingOrder(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Ct:Block', [], [], [
            'content' => [new StoredElement('first', 'Ct:Card')],
        ])]);
        $elements = [new StoredElement('a', 'Ct:Block'), new StoredElement('b', 'Ct:Card')];

        $result = $this->op($elements, 'parent', 'content', 0)->apply($tree);

        $children = $result->roots[0]->slots['content'];
        static::assertSame(
            ['Ct:Block', 'Ct:Card', 'Ct:Card'],
            array_map(static fn (StoredElement $child): string => $child->component, $children),
        );
        static::assertSame('first', $children[2]->id);
    }

    #[TestDox('an empty element list leaves the tree untouched')]
    public function testEmptyListInsertsNothing(): void
    {
        $op = $this->op([]);
        $result = $op->apply(new StoredTree([new StoredElement('existing', 'Ct:Block')]));

        static::assertCount(1, $result->roots);
        static::assertSame([], $op->affected());
    }

    /**
     * @param list<StoredElement> $elements
     */
    private function op(array $elements, ?string $parentElementId = null, ?string $slot = null, ?int $index = null): AttachElements
    {
        return new AttachElements(
            $this->registry(),
            $elements,
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            new BindingApplicator(
                static::createStub(DataLoaderConfigSerializerProvider::class),
                static::createStub(AbstractContentSystemElementTypeRegistry::class),
            ),
            $parentElementId,
            $slot,
            $index,
        );
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $registered = ['Ct:Card', 'Ct:Block'];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => \in_array($name, $registered, true));

        return $registry;
    }
}
