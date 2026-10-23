<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Cms\Type;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\ImageStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver;
use Contena\Core\Content\Media\Cms\ImageCmsElementResolver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ImageTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ImageCmsElementResolver $imageResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->imageResolver = new ImageCmsElementResolver(new DefaultMediaResolver($this->publicFilesystem));
    }

    public function testType(): void
    {
        static::assertSame('image', $this->imageResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->imageResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithMediaId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->imageResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedMediaCustomFieldId(): void
    {
        $blog = new BlogEntity();
        $blog->setCustomFields(['heroImage' => 'media123']);

        $resolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroImage'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->imageResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);

        $expectedCriteria = new Criteria(['media123']);
        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertNull($imageStruct->getUrl());
        static::assertNull($imageStruct->getMedia());
        static::assertNull($imageStruct->getMediaId());
    }

    public function testEnrichWithUrlOnly(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://contena.cn/image.jpg'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['url' => 'http://contena.cn/image.jpg']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('http://contena.cn/image.jpg', $imageStruct->getUrl());
        static::assertNull($imageStruct->getMedia());
        static::assertNull($imageStruct->getMediaId());
    }

    public function testEnrichWithUrlAndNewTabOnly(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://contena.cn/image.jpg'));
        $fieldConfig->add(new FieldConfig('newTab', FieldConfig::SOURCE_STATIC, true));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['url' => 'http://contena.cn/image.jpg']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('http://contena.cn/image.jpg', $imageStruct->getUrl());
        static::assertTrue($imageStruct->getNewTab());
        static::assertNull($imageStruct->getMedia());
        static::assertNull($imageStruct->getMediaId());
    }

    public function testEnrichWithMediaOnly(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['media' => 'media123', 'source' => FieldConfig::SOURCE_STATIC]);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertNull($imageStruct->getUrl());
        static::assertInstanceOf(MediaEntity::class, $imageStruct->getMedia());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithMediaAndUrl(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://contena.cn/image.jpg'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123', 'url' => 'http://contena.cn/image.jpg']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('http://contena.cn/image.jpg', $imageStruct->getUrl());
        static::assertInstanceOf(MediaEntity::class, $imageStruct->getMedia());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithMissingMediaId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            'media',
            0,
            new MediaCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertNull($imageStruct->getUrl());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertNull($imageStruct->getMedia());
    }

    public function testEnrichWithDefaultConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $this->publicFilesystem->write('/bundles/core/assets/default/cms/contena.jpg', '');

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'bundles/core/assets/default/cms/contena.jpg'));

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        $media = $imageStruct->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('contena', $media->getFileName());
        static::assertSame('image/jpeg', $media->getMimeType());
        static::assertSame('jpg', $media->getFileExtension());
    }

    public function testMediaWithRemote(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(json_decode(json_encode($fieldConfig, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertNull($imageStruct->getUrl());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testMediaWithLocal(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $blogMedia = new BlogMediaEntity();
        $blogMedia->setMedia($media);

        $blog = new BlogEntity();
        $blog->setCover($blogMedia);

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::createStub(BlogDefinition::class), $blog);

        $mediaSearchResult = new EntitySearchResult(
            'media',
            0,
            new MediaCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'cover.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertNull($imageStruct->getUrl());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testMediaWithMappedCustomFieldId(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $blog = new BlogEntity();
        $blog->setCustomFields(['heroImage' => 'media123']);

        $resolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $mediaSearchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroImage'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testUrlWithLocal(): void
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setLink('http://contena.cn');

        $blog = new BlogEntity();
        $blog->setManufacturer($manufacturer);

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::createStub(BlogDefinition::class), $blog);

        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_MAPPED, 'manufacturer.link'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame($manufacturer->getLink(), $imageStruct->getUrl());
        static::assertNull($imageStruct->getMediaId());
        static::assertNull($imageStruct->getMedia());
    }
}
