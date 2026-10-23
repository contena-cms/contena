<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\ManufacturerLogoStruct;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerCollection;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Cms\ManufacturerLogoCmsElementResolver;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ManufacturerLogoCmsElementResolver::class)]
class ManufacturerLogoCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertSame('manufacturer-logo', $resolver->getType());
    }

    public function testCollectCreatesMediaCriteria(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(MediaDefinition::class, $elements);

        $definitionData = array_shift($elements);
        static::assertCount(1, $definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = array_shift($definitionData);
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testCollectReturnsNullWithEmptyConfig(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, null),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectionWithMediaIsEmptyAndManufacturerIsNotLoaded(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $blog = new ChannelBlogEntity();
        $blog->setId('blog-1');
        $blog->setManufacturerId('manufacturer-1');

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            new ChannelBlogDefinition(),
            $blog
        );

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);
        static::assertArrayHasKey(BlogManufacturerDefinition::class, $collection->all());
        $criteria = $collection->all()[BlogManufacturerDefinition::class]['mapped_product_manufacturer_slot-1'];
        static::assertSame(['manufacturer-1'], $criteria->getIds());
        static::assertArrayHasKey('media', $criteria->getAssociations());
    }

    public function testCollectionWithMediaIsEmptyAndManufacturerIsLoadedAndHasNoMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-1');

        $blog = new ChannelBlogEntity();
        $blog->setId('blog-1');
        $blog->setManufacturerId('manufacturer-1');
        $blog->setManufacturer($manufacturer);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            new ChannelBlogDefinition(),
            $blog
        );

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);
        static::assertArrayHasKey(BlogManufacturerDefinition::class, $collection->all());
        $criteria = $collection->all()[BlogManufacturerDefinition::class]['mapped_product_manufacturer_slot-1'];
        static::assertSame(['manufacturer-1'], $criteria->getIds());
        static::assertArrayHasKey('media', $criteria->getAssociations());
    }

    public function testCollectSkipsMappedBlogCriteriaWhenMappedMediaAlreadyLoaded(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $media = new MediaEntity();
        $media->setId('media-1');

        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-1');
        $manufacturer->setMedia($media);

        $blog = new ChannelBlogEntity();
        $blog->setId('blog-1');
        $blog->setManufacturerId('manufacturer-1');
        $blog->setManufacturer($manufacturer);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            new ChannelBlogDefinition(),
            $blog
        );

        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsParentCriteriaWhenMediaIsMappedAndBlogIsStatic(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'),
            new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);
        static::assertArrayHasKey(ChannelBlogDefinition::class, $collection->all());
        static::assertArrayHasKey('blog_slot-1', $collection->all()[ChannelBlogDefinition::class]);
    }

    public function testEnrichStaticSlotWithManufacturerLogo(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
            new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://localhost'),
            new FieldConfig('newTab', FieldConfig::SOURCE_STATIC, true),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new MediaCollection([$media]));

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        /** @var ManufacturerLogoStruct $data */
        $data = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $data);
        static::assertSame('media-1', $data->getMediaId());
        static::assertSame('http://localhost', $data->getUrl());
        static::assertTrue($data->getNewTab());

        $media = $data->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media-1', $media->getId());
    }

    public function testEnrichMappedSlotUsesMappedBlogResult(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $resolverContextBlog = new ChannelBlogEntity();
        $resolverContextBlog->setId('blog-1');

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            new ChannelBlogDefinition(),
            $resolverContextBlog
        );

        $mappedMedia = new MediaEntity();
        $mappedMedia->setId('media-from-result');

        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-1');
        $manufacturer->setMedia($mappedMedia);

        $mappedResult = static::createStub(EntitySearchResult::class);
        $mappedResult->method('getEntities')->willReturn(new BlogManufacturerCollection([$manufacturer]));

        $data = new ElementDataCollection();
        $data->add('mapped_product_manufacturer_slot-1', $mappedResult);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        /** @var ManufacturerLogoStruct $struct */
        $struct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $struct);
        static::assertSame('media-from-result', $struct->getMediaId());
        static::assertNotNull($struct->getManufacturer());
        static::assertSame('manufacturer-1', $struct->getManufacturer()->getId());
    }

    public function testEnrichMappedSlotFallsBackToResolverContextBlogWhenMappedBlogNotResolvable(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $media = new MediaEntity();
        $media->setId('media-context');

        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-context');
        $manufacturer->setMedia($media);

        $resolverContextBlog = new ChannelBlogEntity();
        $resolverContextBlog->setId('blog-1');
        $resolverContextBlog->setManufacturer($manufacturer);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            new ChannelBlogDefinition(),
            $resolverContextBlog
        );

        $invalidEntity = new MediaEntity();
        $invalidEntity->setUniqueIdentifier('invalid-1');
        $invalidMappedResult = static::createStub(EntitySearchResult::class);
        $invalidMappedResult->method('getEntities')->willReturn(new MediaCollection([$invalidEntity]));

        $data = new ElementDataCollection();
        $data->add('mapped_product_manufacturer_slot-1', $invalidMappedResult);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        /** @var ManufacturerLogoStruct $struct */
        $struct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $struct);
        static::assertSame('media-context', $struct->getMediaId());
        static::assertNotNull($struct->getManufacturer());
        static::assertSame('manufacturer-context', $struct->getManufacturer()->getId());
    }
}
