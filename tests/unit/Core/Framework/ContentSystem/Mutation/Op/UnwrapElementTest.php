<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UnwrapElement::class)]
class UnwrapElementTest extends TestCase
{
    #[TestDox('replaces the container with its slot children at the root and creates nothing')]
    public function testUnwrapReplacesContainerWithChildren(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'CT:Container', [], [], [
            'content' => [new StoredElement('a', 'CT:Block'), new StoredElement('b', 'CT:Block')],
        ])]);

        $unwrap = new UnwrapElement('container');
        $result = $unwrap->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
        static::assertSame([], $unwrap->created());
    }

    #[TestDox('hoists the children into the parent slot at the container position')]
    public function testUnwrapHoistsIntoParentSlotAtPosition(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'CT:Block', [], [], [
            'content' => [
                new StoredElement('x', 'CT:Block'),
                new StoredElement('container', 'CT:Container', [], [], [
                    'items' => [new StoredElement('a', 'CT:Block'), new StoredElement('b', 'CT:Block')],
                ]),
                new StoredElement('y', 'CT:Block'),
            ],
        ])]);

        $result = new UnwrapElement('container')->apply($tree);

        static::assertSame(['x', 'a', 'b', 'y'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
    }

    #[TestDox('reports the whole hoisted forest as affected, including grandchildren that lose the container scope')]
    public function testUnwrapAffectedAreHoistedSubtrees(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'CT:Container', [], [], [
            'content' => [
                new StoredElement('a', 'CT:Block', [], [], [
                    'inner' => [new StoredElement('grandchild', 'CT:Block')],
                ]),
                new StoredElement('b', 'CT:Block'),
            ],
        ])]);

        $unwrap = new UnwrapElement('container');
        $unwrap->apply($tree);

        static::assertSame(['a', 'grandchild', 'b'], $unwrap->affected());
    }

    #[TestDox('flattens children across all container slots in slot order')]
    public function testUnwrapFlattensAllSlots(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'CT:Container', [], [], [
            'header' => [new StoredElement('a', 'CT:Block')],
            'body' => [new StoredElement('b', 'CT:Block')],
        ])]);

        $result = new UnwrapElement('container')->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
    }

    #[TestDox('reports the removed containers own static properties and consumed wiring, not its provided context')]
    public function testUnwrapReportsContainerOwnConfig(): void
    {
        $container = new StoredElement(
            'container',
            'CT:Container',
            ['hero' => new DataRequirement('hero', 'entity', new StubLoaderConfig())],
            ['title' => StoredValue::ofString('Section'), 'spacing' => StoredValue::ofInt(3)],
            ['content' => [new StoredElement('kid', 'CT:Block')]],
            new ContextDefinitions(
                ['themeProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['theme' => new ContextConsumer(ContextType::Single, true)],
            ),
        );

        $unwrap = new UnwrapElement('container');
        $unwrap->apply(new StoredTree([$container]));

        static::assertSame(
            ['title' => 'Section', 'spacing' => 3],
            array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $unwrap->droppedProperties())
        );
        static::assertSame(['hero', 'theme'], $unwrap->droppedWiring());
    }

    #[TestDox('removes an empty container and hoists nothing')]
    public function testUnwrapEmptyContainerJustRemovesIt(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'CT:Container'), new StoredElement('keep', 'CT:Block')]);

        $unwrap = new UnwrapElement('container');
        $result = $unwrap->apply($tree);

        static::assertSame(['keep'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
        static::assertSame([], $unwrap->affected());
    }

    #[TestDox('rejects unwrapping a container absent from the tree with a 400')]
    public function testUnwrapMissingContainerRejected(): void
    {
        $unwrap = new UnwrapElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $unwrap->apply(new StoredTree([new StoredElement('other', 'CT:Block')]));
    }
}
