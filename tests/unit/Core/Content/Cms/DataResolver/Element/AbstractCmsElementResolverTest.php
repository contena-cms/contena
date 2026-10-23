<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Channel\Struct\ImageSliderItemStruct;
use Contena\Core\Content\Cms\Channel\Struct\ImageSliderStruct;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
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
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog');

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($blog, 'parent.manufacturer.description');

        static::assertNull($actual);
    }

    public function testResolveNestedEntityValue(): void
    {
        $expected = 'manufacturerDescriptionValue';
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setDescription($expected);

        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog');
        $blog->setManufacturer($manufacturer);

        $childBlog = new BlogEntity();
        $childBlog->setUniqueIdentifier('childBlog');
        $childBlog->setParent($blog);

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($childBlog, 'parent.manufacturer.description');

        static::assertSame($expected, $actual);
    }

    public function testResolveTranslationValue(): void
    {
        $expected = 'Je suis un texte français';
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setTranslated(['description' => $expected]);

        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog');
        $blog->setManufacturer($manufacturer);

        $actual = (new StubCmsElementResolver())->runResolveEntityValue($blog, 'manufacturer.description');

        static::assertSame($expected, $actual);
    }

    public function testResolveNestedTranslationValue(): void
    {
        $expected = 'Je suis un texte français';

        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setTranslated(['description' => $expected]);

        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog');
        $blog->setManufacturer($manufacturer);

        $childBlog = new BlogEntity();
        $childBlog->setUniqueIdentifier('childBlog');
        $childBlog->setParent($blog);
        $childBlog->setTranslated(['translatedDescription' => 'something went wrong']);

        $cmsElementResolver = new StubCmsElementResolver();
        $actualTranslation = $cmsElementResolver->runResolveEntityValue($childBlog, 'parent.manufacturer.description');
        $actualName = $cmsElementResolver->runResolveEntityValue($childBlog, 'parent.manufacturer.name');

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
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog');

        $this->expectExceptionObject(new PropertyNotFoundException('doesntActuallyExist', BlogEntity::class));
        (new StubCmsElementResolver())->runResolveEntityValue($blog, 'that.doesntActuallyExist');
    }

    public function testResolveEntityValueToString(): void
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setUpdatedAt(new \DateTimeImmutable());

        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog');
        $blog->setManufacturer($manufacturer);

        $childBlog = new BlogEntity();
        $childBlog->setUniqueIdentifier('childBlog');
        $childBlog->setParent($blog);

        $context = $this->getEntityResolverContext($blog);

        $actual = (new StubCmsElementResolver())->runResolveEntityValueToString(
            $childBlog,
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
        ?BlogEntity $blog = null,
        ?EntityDefinition $definition = null
    ): EntityResolverContext {
        if (!$blog) {
            $blog = new BlogEntity();
            $blog->setUniqueIdentifier('blog');
        }

        return new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            $definition ?? $this->definition,
            $blog
        );
    }
}
