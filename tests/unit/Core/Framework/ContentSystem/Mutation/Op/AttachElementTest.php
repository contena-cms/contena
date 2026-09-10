<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Contena\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttachElement::class)]
class AttachElementTest extends TestCase
{
    #[TestDox('appends the supplied subtree at the root with a server-minted id')]
    public function testAttachesAtRootWithMintedId(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'Ct:Block')]);

        $result = $this->attach(new StoredElement('incoming', 'Ct:Card'))->apply($tree);

        static::assertCount(2, $result->roots);
        static::assertSame('existing', $result->roots[0]->id);
        static::assertSame('Ct:Card', $result->roots[1]->component);
        static::assertNotSame('incoming', $result->roots[1]->id);
        static::assertTrue(Uuid::isValid($result->roots[1]->id));
    }

    #[TestDox('remints every id in the supplied subtree, never trusting client ids')]
    public function testRemintsEverySubtreeId(): void
    {
        $incoming = new StoredElement('incoming', 'Ct:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Ct:Card')],
        ]);

        $result = $this->attach($incoming)->apply(new StoredTree([]));

        $attached = $result->roots[0];
        $child = $attached->slots['content'][0];
        static::assertNotSame('incoming', $attached->id);
        static::assertNotSame('incoming-child', $child->id);
        static::assertSame('Ct:Card', $child->component);
    }

    #[TestDox('reports every reminted subtree id as affected')]
    public function testAffectedAreMintedSubtreeIds(): void
    {
        $incoming = new StoredElement('incoming', 'Ct:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Ct:Card')],
        ]);

        $attach = $this->attach($incoming);
        $result = $attach->apply(new StoredTree([]));

        $attached = $result->roots[0];
        static::assertSame([$attached->id, $attached->slots['content'][0]->id], $attach->affected());
    }

    #[TestDox('reports every reminted subtree id as created, because the whole splice is new to the layout')]
    public function testCreatedAreEveryMintedSubtreeId(): void
    {
        $incoming = new StoredElement('incoming', 'Ct:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Ct:Card'), new StoredElement('incoming-sibling', 'Ct:Card')],
        ]);

        $attach = $this->attach($incoming);
        $result = $attach->apply(new StoredTree([]));

        $attached = $result->roots[0];
        static::assertSame(
            [$attached->id, $attached->slots['content'][0]->id, $attached->slots['content'][1]->id],
            $attach->created(),
        );
    }

    #[TestDox('attaches the subtree into a parent slot at an explicit index')]
    public function testAttachesIntoParentSlotAtIndex(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Ct:Block', [], [], [
            'content' => [new StoredElement('first', 'Ct:Card')],
        ])]);

        $result = $this->attach(new StoredElement('incoming', 'Ct:Card'), 'parent', 'content', 0)->apply($tree);

        $children = $result->roots[0]->slots['content'];
        static::assertCount(2, $children);
        static::assertNotSame('incoming', $children[0]->id);
        static::assertSame('first', $children[1]->id);
    }

    #[TestDox('clamps an out-of-range index, appending the supplied subtree to the end of the target list')]
    public function testAttachClampsOutOfRangeIndex(): void
    {
        $tree = new StoredTree([new StoredElement('block-a', 'Ct:Card'), new StoredElement('block-b', 'Ct:Card')]);

        $result = $this->attach(new StoredElement('incoming', 'Ct:Card'), null, null, 99)->apply($tree);

        static::assertCount(3, $result->roots);
        static::assertSame(['block-a', 'block-b'], [$result->roots[0]->id, $result->roots[1]->id]);
        static::assertNotSame('incoming', $result->roots[2]->id);
    }

    #[TestDox('detaches nothing: orphaned and dropped wiring stay empty')]
    public function testAttachDetachesNothing(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Ct:Card'));
        $attach->apply(new StoredTree([]));

        static::assertSame([], $attach->orphaned());
        static::assertSame([], $attach->droppedWiring());
    }

    #[TestDox('rejects an unregistered root component with a 400')]
    public function testAttachUnregisteredComponentRejected(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Ct:Ghost'));

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Ct:Ghost'));
        $attach->apply(new StoredTree([]));
    }

    #[TestDox('rejects attaching into a parent absent from the tree with a 400')]
    public function testAttachIntoMissingParentRejected(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Ct:Card'), 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $attach->apply(new StoredTree([new StoredElement('other', 'Ct:Block')]));
    }

    #[TestDox('rejects attaching into a parent without naming a slot with a 400')]
    public function testAttachIntoParentWithoutSlotRejected(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Ct:Card'), 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $attach->apply(new StoredTree([new StoredElement('parent', 'Ct:Block')]));
    }

    private function attach(StoredElement $element, ?string $parentElementId = null, ?string $slot = null, ?int $index = null): AttachElement
    {
        return new AttachElement(
            $this->registry(),
            $element,
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class)),
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
