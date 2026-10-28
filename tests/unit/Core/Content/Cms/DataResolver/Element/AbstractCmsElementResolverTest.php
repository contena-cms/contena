<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Contena\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element\Fixtures\StubCmsElementResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractCmsElementResolver::class)]
class AbstractCmsElementResolverTest extends TestCase
{
    private DefinitionInstanceRegistry&Stub $registry;

    private TestEntityDefinition $definition;

    protected function setUp(): void
    {
        $this->registry = static::createStub(DefinitionInstanceRegistry::class);
        $this->definition = new TestEntityDefinition();
        $this->definition->compile($this->registry);
    }

    public function testResolveEmptyValue(): void
    {
        $actual = (new StubCmsElementResolver())->runResolveEntityValue(null, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityNullValue(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($product, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityValue(): void
    {
        $expected = 'manufacturerDescriptionValue';
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setDescription($expected);

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $childProduct = new ProductEntity();
        $childProduct->setUniqueIdentifier('childProduct');
        $childProduct->setParent($product);

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($childProduct, 'parent.manufacturer.description');

        static::assertSame($expected, $actual);
    }

    public function testResolveTranslationValue(): void
    {
        $expected = 'Je suis un texte français';
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setTranslated(['description' => $expected]);

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($product, 'manufacturer.description');

        static::assertSame($expected, $actual);
    }

    public function testResolveNestedTranslationValue(): void
    {
        $expected = 'Je suis un texte français';

        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setTranslated(['description' => $expected]);

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $childProduct = new ProductEntity();
        $childProduct->setUniqueIdentifier('childProduct');
        $childProduct->setParent($product);
        $childProduct->setTranslated(['translatedDescription' => 'something went wrong']);

        $cmsElementResolver = new StubCmsElementResolver();
        $actualTranslation = $cmsElementResolver->runResolveEntityValue($childProduct, 'parent.manufacturer.description');
        $actualName = $cmsElementResolver->runResolveEntityValue($childProduct, 'parent.manufacturer.name');

        static::assertSame($expected, $actualTranslation);
        static::assertNull($actualName);
    }

    public function testResolveNestedStructValue(): void
    {
        $expected = 'workingUrl';

        $sliderItem = new ImageSliderItemStruct();
        $sliderItem->setUrl($expected);

        $imageSliderStruct = new ImageSliderStruct();
        $imageSliderStruct->setSliderItems([$sliderItem]);

        $entity = new Entity();
        $entity->addExtension('imageSlider', $imageSliderStruct);

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($entity, 'imageSlider.sliderItems.0.url');

        static::assertSame($expected, $actual);
    }

    public function testResolveInvalidArgumentException(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');

        $this->expectExceptionObject(new PropertyNotFoundException('doesntActuallyExist', ProductEntity::class));
        (new StubCmsElementResolver())->runResolveEntityValue($product, 'that.doesntActuallyExist');
    }

    public function testResolveEntityValueToString(): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setUpdatedAt(new \DateTimeImmutable());

        $product = new ProductEntity();
        $product->setUniqueIdentifier('product');
        $product->setManufacturer($manufacturer);

        $childProduct = new ProductEntity();
        $childProduct->setUniqueIdentifier('childProduct');
        $childProduct->setParent($product);

        $context = $this->getEntityResolverContext($product);

        $actual = (new StubCmsElementResolver())->runResolveEntityValueToString(
            $childProduct,
            'parent.manufacturer.updatedAt',
            $context
        );

        try {
            $actual = new \DateTimeImmutable($actual);
            static::assertIsInt($actual->getTimestamp());
        } catch (\Exception) {
            static::fail('Entity value is not a valid date time');
        }
    }

    private function getEntityResolverContext(
        ?ProductEntity $product = null,
        ?EntityDefinition $definition = null
    ): EntityResolverContext {
        if (!$product) {
            $product = new ProductEntity();
            $product->setUniqueIdentifier('product');
        }

        return new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $definition ?? $this->definition,
            $product
        );
    }
}
