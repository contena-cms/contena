<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Contena\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoveElement::class)]
class MoveElementTest extends TestCase
{
    #[TestDox('relocates the element and its subtree into the new parent slot, carries the parent attributed specifications over to the rebuilt parent, reports the whole moved subtree as affected, and creates nothing')]
    public function testMoveRelocatesSubtreeToNewParent(): void
    {
        $target = StoredElementBuilder::create('Ct:Block', 'target')
            ->withAttributedSpecification('blog', 'spec-1')
            ->build();
        $tree = new StoredTree([
            new StoredElement('movable', 'Ct:Block', [], [], [
                'content' => [new StoredElement('child', 'Ct:Block')],
            ]),
            $target,
        ]);

        $move = new MoveElement('movable', 'target', 'content');
        $result = $move->apply($tree);

        static::assertCount(1, $result->roots);
        static::assertSame('target', $result->roots[0]->id);
        static::assertSame(['blog' => 'spec-1'], $result->roots[0]->attributedSpecifications);
        $moved = $result->roots[0]->slots['content'];
        static::assertSame('movable', $moved[0]->id);
        static::assertSame('child', $moved[0]->slots['content'][0]->id);
        static::assertSame(['movable', 'child'], $move->affected());
        static::assertSame([], $move->created());
    }

    #[TestDox('reuses the element current slot for a same-parent move that omits the new slot')]
    public function testMoveSameParentWithoutSlotReusesCurrentSlot(): void
    {
        $parent = new StoredElement('parent', 'Ct:Block', [], [], [
            'content' => [
                new StoredElement('a', 'Ct:Block'),
                new StoredElement('child', 'Ct:Block'),
            ],
        ]);

        $move = new MoveElement('child', 'parent', null, 0);
        $result = $move->apply(new StoredTree([$parent]));

        static::assertSame(['child', 'a'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots['content']));
        static::assertSame([], $move->affected());
    }

    /**
     * @param array<string, list<StoredElement>> $slots
     * @param list<string> $expectedOrder
     */
    #[DataProvider('sameParentMoveProvider')]
    #[TestDox('treats a same-parent move as a pure structural change with empty affected: $_dataName')]
    public function testSameParentMoveReportsEmptyAffected(
        array $slots,
        ContextDefinitions $contextDefinitions,
        string $movedId,
        ?string $newSlot,
        ?int $newIndex,
        string $expectedSlot,
        array $expectedOrder,
    ): void {
        $parent = new StoredElement('parent', 'Ct:Block', [], [], $slots, $contextDefinitions);

        $move = new MoveElement($movedId, 'parent', $newSlot, $newIndex);
        $result = $move->apply(new StoredTree([$parent]));

        static::assertSame(
            $expectedOrder,
            array_map(static fn (StoredElement $e): string => $e->id, $result->roots[0]->slots[$expectedSlot])
        );
        static::assertSame([], $move->affected());
    }

    /**
     * @return iterable<string, array{array<string, list<StoredElement>>, ContextDefinitions, string, ?string, ?int, string, list<string>}>
     */
    public static function sameParentMoveProvider(): iterable
    {
        yield 'reorder within the same slot, even under an indexed distribution' => [
            [
                'content' => [
                    new StoredElement('a', 'Ct:Block'),
                    new StoredElement('b', 'Ct:Block'),
                ],
            ],
            new ContextDefinitions(['list' => new ContextProvider(ContextType::Single, IndexedDistributionConfig::simple())], []),
            'b',
            'content',
            0,
            'content',
            ['b', 'a'],
        ];

        yield 'move to a different slot under the same parent' => [
            [
                'left' => [new StoredElement('child', 'Ct:Block')],
                'right' => [],
            ],
            new ContextDefinitions(),
            'child',
            'right',
            null,
            'right',
            ['child'],
        ];
    }

    #[TestDox('moves a nested element out to the root and reports the moved subtree as affected')]
    public function testMoveToRootDetachesFromParent(): void
    {
        $tree = new StoredTree([
            new StoredElement('parent', 'Ct:Block', [], [], [
                'content' => [new StoredElement('movable', 'Ct:Block')],
            ]),
        ]);

        $move = new MoveElement('movable');
        $result = $move->apply($tree);

        static::assertSame(['parent', 'movable'], array_map(static fn (StoredElement $e): string => $e->id, $result->roots));
        static::assertSame(['movable'], $move->affected());
    }

    /**
     * @param non-empty-string $newParentId
     */
    #[DataProvider('rejectsCycleTargetProvider')]
    #[TestDox('rejects moving an element onto itself or one of its descendants')]
    public function testMoveOntoSelfOrDescendantRejected(string $newParentId): void
    {
        $tree = new StoredTree([
            new StoredElement('movable', 'Ct:Block', [], [], [
                'content' => [new StoredElement('child', 'Ct:Block')],
            ]),
        ]);

        $move = new MoveElement('movable', $newParentId, 'content');

        $this->expectExceptionObject(ContentSystemException::mutationCycle('movable'));
        $move->apply($tree);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsCycleTargetProvider(): iterable
    {
        yield 'onto itself' => ['movable'];
        yield 'onto a descendant' => ['child'];
    }

    #[TestDox('rejects moving an element absent from the tree with a 400')]
    public function testMoveMissingElementRejected(): void
    {
        $move = new MoveElement('ghost', 'target', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $move->apply(new StoredTree([new StoredElement('target', 'Ct:Block')]));
    }

    #[TestDox('rejects moving into a parent absent from the tree with a 400')]
    public function testMoveToMissingParentRejected(): void
    {
        $move = new MoveElement('movable', 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $move->apply(new StoredTree([new StoredElement('movable', 'Ct:Block')]));
    }

    #[TestDox('rejects a cross-parent move without a slot with a 400')]
    public function testMoveToNewParentWithoutSlotRejected(): void
    {
        $tree = new StoredTree([
            new StoredElement('movable', 'Ct:Block'),
            new StoredElement('target', 'Ct:Block'),
        ]);

        $move = new MoveElement('movable', 'target');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $move->apply($tree);
    }
}
