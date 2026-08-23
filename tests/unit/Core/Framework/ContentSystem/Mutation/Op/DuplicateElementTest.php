<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Contena\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(DuplicateElement::class)]
class DuplicateElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('inserts the clone as the next sibling with a fresh id')]
    public function testDuplicateInsertsCloneAsNextSibling(): void
    {
        $tree = [new ContentElement('original', 'CT:Card'), new ContentElement('other', 'CT:Block')];

        $result = new DuplicateElement('original')->apply($tree);

        static::assertCount(3, $result);
        static::assertSame('original', $result[0]->getId());
        static::assertSame('CT:Card', $result[1]->getComponent());
        static::assertNotSame('original', $result[1]->getId());
        static::assertSame('other', $result[2]->getId());
    }

    #[TestDox('remints every id in the cloned subtree')]
    public function testDuplicateRemintsEverySubtreeId(): void
    {
        $tree = [new ContentElement('root', 'CT:Block', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'CT:Block')]),
        ])];

        $result = new DuplicateElement('root')->apply($tree);

        $clone = $result[1];
        $clonedChild = array_values($clone->getSlots()['content']->getElements())[0];
        static::assertNotSame('root', $clone->getId());
        static::assertNotSame('child', $clonedChild->getId());
        static::assertSame('CT:Block', $clonedChild->getComponent());
    }

    #[TestDox('reports the cloned subtree ids as affected')]
    public function testDuplicateAffectedAreCloneIds(): void
    {
        $tree = [new ContentElement('root', 'CT:Block', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'CT:Block')]),
        ])];

        $duplicate = new DuplicateElement('root');
        $result = $duplicate->apply($tree);

        $clone = $result[1];
        $clonedChild = array_values($clone->getSlots()['content']->getElements())[0];
        static::assertSame([$clone->getId(), $clonedChild->getId()], $duplicate->affected());
    }

    #[TestDox('reports only the clone id as affected when the duplicated element has no children')]
    public function testDuplicateLeafAffectedIsCloneIdOnly(): void
    {
        $tree = [new ContentElement('original', 'CT:Card'), new ContentElement('other', 'CT:Block')];

        $duplicate = new DuplicateElement('original');
        $result = $duplicate->apply($tree);

        $clone = $result[1];
        static::assertNotSame('original', $clone->getId());
        static::assertSame('original', $result[0]->getId());
        static::assertSame('CT:Card', $clone->getComponent());
        static::assertSame([], $clone->getProperties());
        static::assertSame([$clone->getId()], $duplicate->affected());
    }

    #[TestDox('carries key-based wiring, context definitions, and style over to the clone unchanged')]
    public function testDuplicatePreservesWiringAndStyle(): void
    {
        $requirement = new DataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $contextDefinitions = new ContextDefinitions(['list' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())], []);
        $style = new ElementStyle(['col-span' => ['md' => 6]]);
        $tree = [new ContentElement('original', 'CT:Card', ['blog' => $requirement], [], [], $contextDefinitions, $style)];

        $result = new DuplicateElement('original')->apply($tree);

        static::assertSame(['blog' => $requirement], $result[1]->getDataRequirements());
        static::assertSame($contextDefinitions, $result[1]->getContextDefinitions());
        static::assertSame($style->toArray(), $result[1]->getStyle()->toArray());
    }

    #[TestDox('carries attributed specifications over to the reconstructed clone unchanged')]
    public function testDuplicatePreservesAttributedSpecificationsOnClone(): void
    {
        $original = ContentElementBuilder::create('CT:Card', 'original')
            ->withAttributedSpecification('blog', 'spec-1')
            ->build();
        $tree = [$original, new ContentElement('other', 'CT:Block')];

        $result = new DuplicateElement('original')->apply($tree);

        $clone = $result[1];
        static::assertNotSame('original', $clone->getId());
        static::assertSame(['blog' => 'spec-1'], $clone->getAttributedSpecifications());
    }

    #[TestDox('duplicates a nested element into the same parent slot')]
    public function testDuplicateNestedElement(): void
    {
        $tree = [new ContentElement('parent', 'CT:Block', [], [], [
            'content' => new SlotContent([new ContentElement('original', 'CT:Card')]),
        ])];

        $result = new DuplicateElement('original')->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertCount(2, $children);
        static::assertSame('original', $children[0]->getId());
        static::assertTrue(Uuid::isValid($children[1]->getId()));
        static::assertSame('CT:Card', $children[1]->getComponent());
    }

    #[TestDox('inserts the clone at an explicit index when given')]
    public function testDuplicateAtExplicitIndex(): void
    {
        $tree = [new ContentElement('original', 'CT:Card'), new ContentElement('other', 'CT:Block')];

        $result = new DuplicateElement('original', 0)->apply($tree);

        static::assertSame('CT:Card', $result[0]->getComponent());
        static::assertSame('original', $result[1]->getId());
        static::assertSame('other', $result[2]->getId());
    }

    #[TestDox('does not mutate the input parent in place when duplicating a nested child')]
    public function testDuplicateDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('parent', 'CT:Block', [], [], [
            'content' => new SlotContent([
                new ContentElement('original', 'CT:Card', [], ['title' => 'Section'], [
                    'inner' => new SlotContent([new ContentElement('child', 'CT:Block')]),
                ]),
            ]),
        ])];
        $before = $this->snapshotTree($tree);

        new DuplicateElement('original')->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('rejects duplicating an element absent from the tree with a 400')]
    public function testDuplicateMissingElementRejected(): void
    {
        $duplicate = new DuplicateElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $duplicate->apply([new ContentElement('other', 'CT:Block')]);
    }
}
