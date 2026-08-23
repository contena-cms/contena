<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Contena\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;

/**
 * @internal
 */
#[CoversClass(UnwrapElement::class)]
class UnwrapElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('replaces the container with its slot children at the root')]
    public function testUnwrapReplacesContainerWithChildren(): void
    {
        $tree = [new ContentElement('container', 'CT:Container', [], [], [
            'content' => new SlotContent([new ContentElement('a', 'CT:Block'), new ContentElement('b', 'CT:Block')]),
        ])];

        $result = new UnwrapElement('container')->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
    }

    #[TestDox('hoists the children into the parent slot at the container position')]
    public function testUnwrapHoistsIntoParentSlotAtPosition(): void
    {
        $tree = [new ContentElement('parent', 'CT:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('x', 'CT:Block'),
                new ContentElement('container', 'CT:Container', [], [], [
                    'items' => new SlotContent([new ContentElement('a', 'CT:Block'), new ContentElement('b', 'CT:Block')]),
                ]),
                new ContentElement('y', 'CT:Block'),
            ]),
        ])];

        $result = new UnwrapElement('container')->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['x', 'a', 'b', 'y'], array_map(static fn (ContentElement $e): string => $e->getId(), $children));
    }

    #[TestDox('reports the whole hoisted forest as affected, including grandchildren that lose the container scope')]
    public function testUnwrapAffectedAreHoistedSubtrees(): void
    {
        $tree = [new ContentElement('container', 'CT:Container', [], [], [
            'content' => new SlotContent([
                new ContentElement('a', 'CT:Block', [], [], [
                    'inner' => new SlotContent([new ContentElement('grandchild', 'CT:Block')]),
                ]),
                new ContentElement('b', 'CT:Block'),
            ]),
        ])];

        $unwrap = new UnwrapElement('container');
        $unwrap->apply($tree);

        static::assertSame(['a', 'grandchild', 'b'], $unwrap->affected());
    }

    #[TestDox('reports the removed containers own static properties and consumed wiring, not its provided context')]
    public function testUnwrapReportsContainerOwnConfig(): void
    {
        $container = new ContentElement(
            'container',
            'CT:Container',
            ['hero' => new DataRequirement('hero', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))],
            ['title' => 'Section', 'spacing' => 3],
            ['content' => new SlotContent([new ContentElement('kid', 'CT:Block')])],
            new ContextDefinitions(
                ['themeProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['theme' => new ContextConsumer(ContextType::Single, true)],
            ),
        );

        $unwrap = new UnwrapElement('container');
        $unwrap->apply([$container]);

        static::assertSame(['title' => 'Section', 'spacing' => 3], $unwrap->droppedProperties());
        static::assertSame(['hero', 'theme'], $unwrap->droppedWiring());
    }

    #[TestDox('flattens children across all container slots in slot order')]
    public function testUnwrapFlattensAllSlots(): void
    {
        $tree = [new ContentElement('container', 'CT:Container', [], [], [
            'header' => new SlotContent([new ContentElement('a', 'CT:Block')]),
            'body' => new SlotContent([new ContentElement('b', 'CT:Block')]),
        ])];

        $result = new UnwrapElement('container')->apply($tree);

        static::assertSame(['a', 'b'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
    }

    #[TestDox('removes an empty container and hoists nothing')]
    public function testUnwrapEmptyContainerJustRemovesIt(): void
    {
        $tree = [new ContentElement('container', 'CT:Container'), new ContentElement('keep', 'CT:Block')];

        $unwrap = new UnwrapElement('container');
        $result = $unwrap->apply($tree);

        static::assertSame(['keep'], array_map(static fn (ContentElement $e): string => $e->getId(), $result));
        static::assertSame([], $unwrap->affected());
    }

    #[TestDox('rejects unwrapping a container absent from the tree with a 400')]
    public function testUnwrapMissingContainerRejected(): void
    {
        $unwrap = new UnwrapElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $unwrap->apply([new ContentElement('other', 'CT:Block')]);
    }

    #[TestDox('does not mutate the input tree')]
    public function testUnwrapDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('container', 'CT:Container', [], ['title' => 'Section'], [
            'content' => new SlotContent([new ContentElement('a', 'CT:Block')]),
        ])];
        $before = $this->snapshotTree($tree);

        new UnwrapElement('container')->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }
}
