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
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\StoredTree;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Contena\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BindElement::class)]
class BindElementTest extends TestCase
{
    #[TestDox('inlines the resolves entry as a DataRequirement, seeds the input default, and attributes the resolves key to the specification id')]
    public function testBindWiresResolvesSeedsDefaultsAndAttributesSpecification(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = new StoredTree([new StoredElement('el', 'Ct:Blog')]);

        $result = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config))->apply($tree);

        static::assertEquals(['blog' => new DataRequirement('blog', 'entity', $config)], $result->roots[0]->dataRequirements);
        static::assertSame('123', $result->roots[0]->property('mediaId')?->jsonSerialize());
        static::assertSame(['blog' => 'spec-1'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('does not seed a property when the input has no default and the element lacks the key')]
    public function testBindDoesNotSeedInputWithoutDefaultForAbsentKey(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = new StoredTree([new StoredElement('el', 'Ct:Blog')]);

        $result = new BindElement($this->registryWithoutInputDefault(), 'spec-1', 'el', $this->applicator($config))->apply($tree);

        static::assertNull($result->roots[0]->property('mediaId'));
    }

    #[TestDox('does not overwrite an authored non-null value on the input key with the default')]
    public function testBindKeepsAuthoredValueOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = StoredElementBuilder::create('Ct:Blog', 'el')->withProperty('mediaId', 'authored')->build();

        $result = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config))->apply(new StoredTree([$old]));

        static::assertSame('authored', $result->roots[0]->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('replaces the wiring and attribution of a key already bound by a different specification')]
    public function testBindReplacesWiringAndAttributionOfAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = StoredElementBuilder::create('Ct:Blog', 'el')
            ->withDataRequirement('blog', 'entity', $oldConfig)
            ->withAttributedSpecification('blog', 'spec-old')
            ->withProperty('mediaId', 'user-filled')
            ->build();

        $result = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($newConfig))->apply(new StoredTree([$old]));

        static::assertEquals(['blog' => new DataRequirement('blog', 'entity', $newConfig)], $result->roots[0]->dataRequirements);
        static::assertSame(['blog' => 'spec-1'], $result->roots[0]->attributedSpecifications);
        static::assertSame('user-filled', $result->roots[0]->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('reports the bound element as affected')]
    public function testReportsAffectedElementAndNoDetachment(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config));

        $bind->apply(new StoredTree([new StoredElement('el', 'Ct:Blog')]));

        // Bind only rewires the existing node: it creates nothing, and never detaches anything, so
        // orphaned()/droppedWiring()/droppedProperties() are always empty for this operation.
        static::assertSame(['el'], $bind->affected());
        static::assertSame([], $bind->created());
    }

    #[TestDox('does not overwrite an authored explicit null on the input key with the default')]
    public function testBindKeepsAuthoredExplicitNullOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = StoredElementBuilder::create('Ct:Blog', 'el')->withProperty('mediaId', null)->build();

        $result = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config))->apply(new StoredTree([$old]));

        static::assertTrue($result->roots[0]->property('mediaId')?->isNull());
    }

    #[TestDox('rejects a specification whose type does not match the target element component with a 400')]
    public function testBindTypeMismatchRejected(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config));

        $this->expectExceptionObject(ContentSystemException::bindingTypeMismatch('spec-1', 'Ct:Blog', 'Ct:Other'));
        $bind->apply(new StoredTree([new StoredElement('el', 'Ct:Other')]));
    }

    #[TestDox('rejects an unknown binding specification id with a 400')]
    public function testBindUnknownSpecificationIdRejected(): void
    {
        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn([]);
        $bind = new BindElement($registry, 'ghost', 'el', $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationNotFound('ghost'));
        $bind->apply(new StoredTree([new StoredElement('el', 'Ct:Blog')]));
    }

    #[TestDox('rejects binding an element absent from the tree with a 400')]
    public function testBindMissingElementRejected(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry(), 'spec-1', 'ghost', $this->applicator($config));

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $bind->apply(new StoredTree([new StoredElement('el', 'Ct:Blog')]));
    }

    private function registry(): AbstractContentSystemBindingSpecificationRegistry
    {
        $specification = new BindingSpecification(
            'spec-1',
            'Ct:Blog',
            'Blog binding',
            ['blog' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => new BindingInput(true, '123', false)],
            'core',
        );

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn(['spec-1' => $specification]);

        return $registry;
    }

    private function registryWithoutInputDefault(): AbstractContentSystemBindingSpecificationRegistry
    {
        $specification = new BindingSpecification(
            'spec-1',
            'Ct:Blog',
            'Blog binding',
            ['blog' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => new BindingInput(false, null, false)],
            'core',
        );

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn(['spec-1' => $specification]);

        return $registry;
    }

    private function applicator(AbstractContentDataLoaderConfig $config): BindingApplicator
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn($config);

        return new BindingApplicator($serializers, static::createStub(AbstractContentSystemElementTypeRegistry::class));
    }
}
