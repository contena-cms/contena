<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RemoveElement::class)]
class RemoveElementTest extends TestCase
{
    #[TestDox('deletes the element together with its whole subtree and reports no affected survivor or created node')]
    public function testRemoveDeletesElementAndSubtree(): void
    {
        $tree = new StoredTree([
            new StoredElement('keep', 'CT:Block'),
            new StoredElement('drop', 'CT:Block', [], [], [
                'content' => [new StoredElement('child', 'CT:Block')],
            ]),
        ]);

        $remove = new RemoveElement('drop');
        $result = $remove->apply($tree);

        static::assertCount(1, $result->roots);
        static::assertSame('keep', $result->roots[0]->id);
        static::assertSame([], $remove->affected());
        static::assertSame([], $remove->created());
    }

    #[TestDox('removes a nested element while keeping its siblings')]
    public function testRemoveNestedElementKeepsSiblings(): void
    {
        $parent = new StoredElement('parent', 'CT:Block', [], [], [
            'content' => [
                new StoredElement('a', 'CT:Block'),
                new StoredElement('b', 'CT:Block'),
            ],
        ]);

        $result = new RemoveElement('a')->apply(new StoredTree([$parent]));

        static::assertSame(['b'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
    }

    #[TestDox('leaves a surviving element data requirements untouched')]
    public function testRemoveLeavesSurvivorWiringUntouched(): void
    {
        $requirement = new DataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $survivor = new StoredElement('survivor', 'CT:Block', ['blog' => $requirement]);
        $tree = new StoredTree([$survivor, new StoredElement('drop', 'CT:Block')]);

        $result = new RemoveElement('drop')->apply($tree);

        static::assertSame(['blog' => $requirement], $result->roots[0]->dataRequirements);
    }

    #[TestDox('rejects removing an element absent from the tree with a 400')]
    public function testRemoveMissingElementRejected(): void
    {
        $remove = new RemoveElement('ghost');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $remove->apply(new StoredTree([new StoredElement('other', 'CT:Block')]));
    }
}
