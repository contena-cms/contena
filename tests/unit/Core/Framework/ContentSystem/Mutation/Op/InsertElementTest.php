<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingInput;
use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Contena\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Contena\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Contena\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InsertElement::class)]
class InsertElementTest extends TestCase
{
    #[TestDox('appends a fresh element of the type to the root with a server-minted id and no seeded style, and reports that id as the only affected element')]
    public function testInsertAppendsRootElementAndReportsMintedIdAsAffected(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'CT:Block')]);

        $insert = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $insert->apply($tree);

        static::assertCount(2, $result->roots);
        static::assertSame('existing', $result->roots[0]->id);
        static::assertSame('CT:Card', $result->roots[1]->component);
        static::assertTrue(Uuid::isValid($result->roots[1]->id));
        static::assertTrue($result->roots[1]->style->isEmpty());
        static::assertSame([$result->roots[1]->id], $insert->affected());
        static::assertSame([$result->roots[1]->id], $insert->created());
    }

    #[TestDox('splices the new element into a parent slot at the given index')]
    public function testInsertIntoParentSlotAtIndex(): void
    {
        $parent = new StoredElement('parent', 'CT:Block', [], [], [
            'content' => [new StoredElement('a', 'CT:Block'), new StoredElement('b', 'CT:Block')],
        ]);

        $insert = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'parent', slot: 'content', index: 1);
        $result = $insert->apply(new StoredTree([$parent]));

        $children = $result->roots[0]->slots['content'];
        static::assertSame(['a', 'CT:Card', 'b'], [$children[0]->id, $children[1]->component, $children[2]->id]);
    }

    #[TestDox('prepends to the root when index zero is given without a parent')]
    public function testInsertAtRootIndexZero(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'CT:Block')]);

        $insert = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator(), index: 0);
        $result = $insert->apply($tree);

        static::assertSame('CT:Card', $result->roots[0]->component);
        static::assertSame('existing', $result->roots[1]->id);
    }

    #[TestDox('preserves the parent style when inserting into its slot')]
    public function testInsertIntoSlotPreservesParentStyle(): void
    {
        $style = new ElementStyle(['padding' => ['md' => '1rem']]);
        $tree = new StoredTree([new StoredElement('parent', 'CT:Block', [], ['title' => StoredValue::ofString('Section')], [
            'content' => [new StoredElement('a', 'CT:Block')],
        ], new ContextDefinitions([], []), $style)]);

        $result = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'parent', slot: 'content')->apply($tree);

        static::assertSame($style->toArray(), $result->roots[0]->style->toArray());
    }

    #[TestDox('seeds only primitive properties that declare a default')]
    public function testInsertSeedsPrimitiveDefaultsOnly(): void
    {
        $spec = $this->spec('CT:Card', [
            'headline' => $this->primitive('string', 'Hello'),
            'count' => $this->primitive('integer', null),
            'blog' => $this->reference(),
        ]);

        $insert = new InsertElement($this->registry(['CT:Card' => $spec]), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $insert->apply(new StoredTree([]));

        static::assertSame(['headline' => 'Hello'], $this->rawProperties($result->roots[0]));
    }

    #[TestDox('applies the binding specification onto the freshly scaffolded element with its wiring, seeded input default, and attribution')]
    public function testInsertAppliesBindingWiringSeededDefaultAndAttribution(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $spec = new BindingSpecification(
            'media-picker',
            'CT:Media:Image',
            'Media picker',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => new BindingInput(true, 'seeded', false)],
            'core',
        );

        $insert = new InsertElement(
            $this->registryWith('CT:Media:Image'),
            'CT:Media:Image',
            $this->bindingRegistry(['core:media-picker' => $spec]),
            $this->applicator($config),
            'core:media-picker',
        );
        $result = $insert->apply(new StoredTree([]));

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result->roots[0]->dataRequirements);
        static::assertSame('seeded', $result->roots[0]->property('mediaId')?->jsonSerialize());
        static::assertSame(['media' => 'core:media-picker'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('does not throw and applies no wiring or attribution when the type has no default specification')]
    public function testInsertWithNoDefaultAppliesNothing(): void
    {
        $insert = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator());
        $result = $insert->apply(new StoredTree([]));

        static::assertSame([], $result->roots[0]->dataRequirements);
        static::assertSame([], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('auto-applies the type default specification onto a fresh insert with no explicit bindingSpecificationId, attributed to its own qualified id')]
    public function testInsertAutoAppliesTypeDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $default = new BindingSpecification(
            'CT:Media:Image',
            'CT:Media:Image',
            'Image',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            [],
            'core',
        );

        $insert = new InsertElement(
            $this->registryWith('CT:Media:Image'),
            'CT:Media:Image',
            $this->bindingRegistry(['core:CT:Media:Image' => $default]),
            $this->applicator($config),
        );
        $result = $insert->apply(new StoredTree([]));

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result->roots[0]->dataRequirements);
        static::assertSame(['media' => 'core:CT:Media:Image'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('fill-applies the type default first, then applies the explicit binding specification on top, attributing the shared key to the explicit choice')]
    public function testInsertExplicitBindingAppliesOnTopOfDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $default = new BindingSpecification(
            'CT:Media:Image',
            'CT:Media:Image',
            'Image',
            [
                'media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId']),
                'gallery' => new LoaderBinding('entity_collection', ['entity' => 'media', 'property' => 'galleryIds']),
            ],
            [],
            'core',
        );
        $explicit = new BindingSpecification('gallery-pick', 'CT:Media:Image', 'Gallery pick', ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'galleryPickId'])], [], 'core');

        $insert = new InsertElement(
            $this->registryWith('CT:Media:Image'),
            'CT:Media:Image',
            $this->bindingRegistry(['core:CT:Media:Image' => $default, 'core:gallery-pick' => $explicit]),
            $this->applicator($config),
            'core:gallery-pick',
        );
        $result = $insert->apply(new StoredTree([]));

        static::assertSame(['media' => 'core:gallery-pick', 'gallery' => 'core:CT:Media:Image'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('rejects an unregistered type with a 400')]
    public function testInsertUnknownTypeRejected(): void
    {
        $insert = new InsertElement($this->registry([]), 'CT:Ghost', $this->bindingRegistry([]), $this->unboundApplicator());

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('CT:Ghost'));
        $insert->apply(new StoredTree([]));
    }

    #[TestDox('rejects a parented insert without a slot with a 400')]
    public function testInsertParentWithoutSlotRejected(): void
    {
        $parent = new StoredElement('parent', 'CT:Block');

        $insert = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $insert->apply(new StoredTree([$parent]));
    }

    #[TestDox('rejects an insert into a missing parent with a 400')]
    public function testInsertMissingParentRejected(): void
    {
        $insert = new InsertElement($this->registryWith('CT:Card'), 'CT:Card', $this->bindingRegistry([]), $this->unboundApplicator(), parentElementId: 'ghost', slot: 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $insert->apply(new StoredTree([new StoredElement('other', 'CT:Block')]));
    }

    #[TestDox('rejects a type with more than one default specification with a 409 naming the colliding qualified ids')]
    public function testInsertWithAmbiguousDefaultThrows(): void
    {
        $first = new BindingSpecification('CT:Media:Image', 'CT:Media:Image', 'Image', [], [], 'core');
        $second = new BindingSpecification('CT:Media:Image', 'CT:Media:Image', 'Image', [], [], 'app1');

        $insert = new InsertElement(
            $this->registryWith('CT:Media:Image'),
            'CT:Media:Image',
            $this->bindingRegistry(['core:CT:Media:Image' => $first, 'app1:CT:Media:Image' => $second]),
            $this->unboundApplicator(),
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDefaultAmbiguous('CT:Media:Image', ['core:CT:Media:Image', 'app1:CT:Media:Image']));
        $insert->apply(new StoredTree([]));
    }

    #[TestDox('rejects an unknown bindingSpecificationId with a 400 before any tree change')]
    public function testInsertUnknownBindingRejected(): void
    {
        $insert = new InsertElement(
            $this->registryWith('CT:Media:Image'),
            'CT:Media:Image',
            $this->bindingRegistry([]),
            $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)),
            'core:ghost',
        );

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationNotFound('core:ghost'));
        $insert->apply(new StoredTree([new StoredElement('existing', 'CT:Block')]));
    }

    #[TestDox('rejects a binding specification whose type does not match the inserted type with a 400 before any tree change')]
    public function testInsertMismatchedBindingTypeRejected(): void
    {
        $spec = new BindingSpecification('media-picker', 'CT:Other', 'label', ['media' => new LoaderBinding('entity', [])], [], 'core');

        $insert = new InsertElement(
            $this->registryWith('CT:Media:Image'),
            'CT:Media:Image',
            $this->bindingRegistry(['core:media-picker' => $spec]),
            $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)),
            'core:media-picker',
        );

        $this->expectExceptionObject(ContentSystemException::bindingTypeMismatch('core:media-picker', 'CT:Other', 'CT:Media:Image'));
        $insert->apply(new StoredTree([new StoredElement('existing', 'CT:Block')]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rawProperties(StoredElement $element): array
    {
        return array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $element->properties());
    }

    private function registryWith(string $type): AbstractContentSystemElementTypeRegistry
    {
        return $this->registry([$type => $this->spec($type, [])]);
    }

    /**
     * @param array<string, BindingSpecification> $specs
     */
    private function bindingRegistry(array $specs): AbstractContentSystemBindingSpecificationRegistry
    {
        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn($specs);

        return $registry;
    }

    private function applicator(AbstractContentDataLoaderConfig $config): BindingApplicator
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn($config);

        return new BindingApplicator($serializers);
    }

    private function unboundApplicator(): BindingApplicator
    {
        return new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class));
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specs
     */
    private function registry(array $specs): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @param array<string, PropertySpecification> $properties
     */
    private function spec(string $name, array $properties): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification($name, $name, '', null, null, new CopilotSpecification('', []), $properties, []);
    }

    private function primitive(string $type, string|int|float|bool|null $default): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, $default), false, '', '', null);
    }

    private function reference(): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType('SomeEntity', false, null, null), false, '', '', null);
    }
}
