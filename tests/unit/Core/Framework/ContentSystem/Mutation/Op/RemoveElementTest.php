<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Contena\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;

/**
 * @internal
 */
#[CoversClass(RemoveElement::class)]
class RemoveElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('deletes the element together with its whole subtree')]
    public function testRemoveDeletesElementAndSubtree(): void
    {
        $tree = [
            new ContentElement('keep', 'CT:Block'),
            new ContentElement('drop', 'CT:Block', [], [], [
                'content' => new SlotContent([new ContentElement('child', 'CT:Block')]),
            ]),
        ];

        $result = new RemoveElement('drop')->apply($tree);

        static::assertCount(1, $result);
        static::assertSame('keep', $result[0]->getId());
    }

    #[TestDox('removes a nested element while keeping its siblings')]
    public function testRemoveNestedElementKeepsSiblings(): void
    {
        $parent = new ContentElement('parent', 'CT:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('a', 'CT:Block'),
                new ContentElement('b', 'CT:Block'),
            ]),
        ]);

        $result = new RemoveElement('a')->apply([$parent]);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame(['b'], array_map(static fn (ContentElement $e): string => $e->getId(), $children));
    }

    #[TestDox('leaves a surviving element data requirements untouched')]
    public function testRemoveLeavesSurvivorWiringUntouched(): void
    {
        $requirement = new DataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $survivor = new ContentElement('survivor', 'CT:Block', ['blog' => $requirement]);
        $tree = [$survivor, new ContentElement('drop', 'CT:Block')];

        $result = new RemoveElement('drop')->apply($tree);

        static::assertSame(['blog' => $requirement], $result[0]->getDataRequirements());
    }

    #[TestDox('reports no affected elements because downward-only context flow strands no survivor')]
    public function testRemoveAffectedIsEmpty(): void
    {
        $remove = new RemoveElement('drop');
        $remove->apply([new ContentElement('drop', 'CT:Block'), new ContentElement('keep', 'CT:Block')]);

        static::assertSame([], $remove->affected());
    }

    #[TestDox('rejects removing an element absent from the tree with a 400')]
    public function testRemoveMissingElementRejected(): void
    {
        $remove = new RemoveElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $remove->apply([new ContentElement('other', 'CT:Block')]);
    }

    #[TestDox('does not mutate the input parent in place when removing a nested child')]
    public function testRemoveDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('parent', 'CT:Block', [], ['title' => 'Section'], [
            'content' => new SlotContent([new ContentElement('a', 'CT:Block'), new ContentElement('b', 'CT:Block')]),
        ])];
        $before = $this->snapshotTree($tree);

        new RemoveElement('a')->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }
}
