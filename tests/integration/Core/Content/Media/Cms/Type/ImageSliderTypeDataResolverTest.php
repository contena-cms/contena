<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Cms\Type;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\ImageSliderStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver;
use Contena\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ImageSliderTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ImageSliderTypeDataResolver $imageSliderResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->imageSliderResolver = new ImageSliderTypeDataResolver(new DefaultMediaResolver($this->publicFilesystem));
    }

    public function testType(): void
    {
        static::assertSame('image-slider', $this->imageSliderResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $medias = [
            ['mediaId' => 'media123'],
            ['mediaId' => 'media456'],
        ];

        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, $medias));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->imageSliderResolver->collect($slot, $resolverContext);
        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123', 'media456']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];
        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigInEntityContextCreatesBlogMediaCriteria(): void
    {
        $resolverContext = $this->getResolverContextWithoutMedia();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertCount(1, iterator_to_array($collection));

        $criteria = $collection->all()[BlogMediaDefinition::class]['blog_media_' . $slot->getUniqueIdentifier()];
        static::assertCount(1, $criteria->getFilters());
        $blogMediaFilter = $criteria->getFilters()[0];
        static::assertInstanceOf(EqualsFilter::class, $blogMediaFilter);
        static::assertSame('blogId', $blogMediaFilter->getField());
        static::assertSame('blog_01', $blogMediaFilter->getValue());
        static::assertArrayHasKey('media', $criteria->getAssociations());
        static::assertCount(1, $criteria->getSorting());
        static::assertSame('position', $criteria->getSorting()[0]->getField());
    }

    public function testCollectWithMappedConfigAndAlreadyResolvedMediaSkipsAdditionalCriteria(): void
    {
        $resolverContext = $this->getResolverContext($this->getBlogMediaCollection());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigAndPartiallyResolvedMediaCreatesAdditionalCriteria(): void
    {
        $resolverContext = $this->getResolverContext($this->getBlogMediaCollectionWithoutAssociatedMedia());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertArrayHasKey(BlogMediaDefinition::class, $collection->all());
        static::assertArrayHasKey('blog_media_id', $collection->all()[BlogMediaDefinition::class]);
    }

    public function testCollectWithDefaultConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_DEFAULT, 'my_default_media.png'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);
        static::assertSame([], $imageSliderStruct->getSliderItems());
    }

    public function testEnrichWithMappedConfigAndHasCorrectOrder(): void
    {
        $blogMediaCollection = $this->getBlogMediaCollection();
        $resolverContext = $this->getResolverContext($blogMediaCollection);
        $result = $this->getEntitySearchResult($blogMediaCollection, $resolverContext);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);

        $expectedSliderIds = ['media0', 'media1', 'media2', 'media3', 'media4'];
        $imageSliderIds = array_map(static fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);

        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    public function testEnrichWithMappedConfigLoadsItemsFromResolverResult(): void
    {
        $blogMediaCollection = $this->getBlogMediaCollection();
        $resolverContext = $this->getResolverContextWithoutMedia();
        $result = new ElementDataCollection();
        $result->add('blog_media_id', new EntitySearchResult(
            'blog_media',
            5,
            $blogMediaCollection,
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);
        static::assertCount(5, $sliderItems);

        $expectedSliderIds = ['media0', 'media1', 'media2', 'media3', 'media4'];
        $imageSliderIds = array_map(fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);
        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    public function testEnrichWithStaticConfig(): void
    {
        $media = new MediaEntity();
        $media->setId('media123');

        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('media_id', new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $medias = [
            ['mediaId' => 'media123'],
        ];

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, $medias));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $imageSliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($imageSliderItems);
        static::assertNotCount(0, $imageSliderItems);

        $firstSliderItem = $imageSliderItems[0];
        static::assertSame($media->getId(), $firstSliderItem->getMedia()?->getId());
    }

    public function testEnrichWithMappedConfigAndHasBlogCoverAtFirstPosition(): void
    {
        $blogMediaCollection = $this->getBlogMediaCollection();
        $resolverContext = $this->getResolverContext($blogMediaCollection, 'media2');
        $result = $this->getEntitySearchResult($blogMediaCollection, $resolverContext);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);

        // Cover image appears at first position
        $expectedSliderIds = ['media2', 'media0', 'media1', 'media3', 'media4'];
        $imageSliderIds = array_map(static fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);

        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    public function testEnrichWithDefaultConfig(): void
    {
        $blogMediaCollection = $this->getBlogMediaCollection();
        $resolverContext = $this->getResolverContext($blogMediaCollection);

        $this->publicFilesystem->write('/bundles/core/assets/default/cms/animated.gif', '');
        $this->publicFilesystem->write('/bundles/core/assets/default/cms/contena.jpg', '');

        $medias = [
            ['fileName' => 'bundles/core/assets/default/cms/animated.gif'],
            ['fileName' => 'bundles/core/assets/default/cms/contena.jpg'],
        ];

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_DEFAULT, $medias));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $result = $this->getEntitySearchResult($blogMediaCollection, $resolverContext);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);
        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $imageSliderItems = $imageSliderStruct->getSliderItems() ?? [];
        static::assertCount(2, $imageSliderItems);

        $firstSliderItem = $imageSliderItems[0];
        $firstSliderItemMedia = $firstSliderItem->getMedia();
        static::assertInstanceOf(MediaEntity::class, $firstSliderItemMedia);
        static::assertSame('animated', $firstSliderItemMedia->getFileName());
        static::assertSame('image/gif', $firstSliderItemMedia->getMimeType());
        static::assertSame('gif', $firstSliderItemMedia->getFileExtension());

        $secondSliderItem = $imageSliderItems[1];
        $secondSliderItemMedia = $secondSliderItem->getMedia();
        static::assertInstanceOf(MediaEntity::class, $secondSliderItemMedia);
        static::assertSame('contena', $secondSliderItemMedia->getFileName());
        static::assertSame('image/jpeg', $secondSliderItemMedia->getMimeType());
        static::assertSame('jpg', $secondSliderItemMedia->getFileExtension());
    }

    public function testEnrichWithCoverIdButWithoutCoverMedia(): void
    {
        $blogMediaCollection = $this->getBlogMediaCollection();
        $resolverContext = $this->getResolverContext($blogMediaCollection, 'nonexistent-media');
        $result = $this->getEntitySearchResult($blogMediaCollection, $resolverContext);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);

        // Cover image appears at first position
        $expectedSliderIds = ['media0', 'media1', 'media2', 'media3', 'media4'];
        $imageSliderIds = array_map(static fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);

        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    protected function getBlogMediaCollection(): BlogMediaCollection
    {
        $blogMedia = [];
        for ($i = 0; $i <= 4; ++$i) {
            $mediaId = 'media' . $i;
            $mediaEntity = new MediaEntity();
            $mediaEntity->setId($mediaId);

            $tempBlogMedia = new BlogMediaEntity();
            $tempBlogMedia->setId($mediaId);
            $tempBlogMedia->setMediaId($mediaId);
            $tempBlogMedia->setMedia($mediaEntity);
            $tempBlogMedia->setPosition($i);

            $blogMedia[] = $tempBlogMedia;
        }

        return new BlogMediaCollection($blogMedia);
    }

    protected function getResolverContext(BlogMediaCollection $blogMediaCollection, ?string $coverId = null): EntityResolverContext
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer_01');

        $blog = new ChannelBlogEntity();
        $blog->setId('blog_01');
        $blog->setManufacturer($manufacturer);
        $blog->setMedia($blogMediaCollection);

        if ($coverId) {
            $blog->setCoverId($coverId);
        }

        if (\is_string($coverId) && $blogMediaCollection->has($coverId)) {
            $cover = new BlogMediaEntity();
            $cover->setId($coverId);
            $blog->setCover($cover);
        }

        return new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::getContainer()->get(ChannelBlogDefinition::class),
            $blog
        );
    }

    protected function getResolverContextWithoutMedia(): EntityResolverContext
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer_01');

        $blog = new ChannelBlogEntity();
        $blog->setId('blog_01');
        $blog->setManufacturer($manufacturer);

        return new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::getContainer()->get(ChannelBlogDefinition::class),
            $blog
        );
    }

    protected function getBlogMediaCollectionWithoutAssociatedMedia(): BlogMediaCollection
    {
        $blogMedia = [];
        for ($i = 0; $i <= 1; ++$i) {
            $mediaId = 'media' . $i;

            $tempBlogMedia = new BlogMediaEntity();
            $tempBlogMedia->setId($mediaId);
            $tempBlogMedia->setMediaId($mediaId);
            $tempBlogMedia->setPosition($i);

            $blogMedia[] = $tempBlogMedia;
        }

        return new BlogMediaCollection($blogMedia);
    }

    protected function getEntitySearchResult(BlogMediaCollection $blogMediaCollection, EntityResolverContext $resolverContext): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add('media_id', new EntitySearchResult(
            'media',
            5,
            $blogMediaCollection->getMedia(),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        return $result;
    }
}
