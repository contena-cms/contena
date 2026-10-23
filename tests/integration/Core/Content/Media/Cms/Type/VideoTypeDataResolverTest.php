<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Cms\Type;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\VideoStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver;
use Contena\Core\Content\Media\Cms\VideoCmsElementResolver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class VideoTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private VideoCmsElementResolver $videoResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->videoResolver = new VideoCmsElementResolver(new DefaultMediaResolver($this->publicFilesystem));
    }

    public function testType(): void
    {
        static::assertSame('video', $this->videoResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->videoResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithMediaId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->videoResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedMediaCustomFieldId(): void
    {
        $blog = new BlogEntity();
        $blog->setCustomFields(['heroVideo' => 'media123']);

        $resolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroVideo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->videoResolver->collect($slot, $resolverContext);

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
        $slot->setType('video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertNull($videoStruct->getMedia());
        static::assertNull($videoStruct->getMediaId());
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
        $slot->setType('video');
        $slot->setConfig(['media' => 'media123', 'source' => FieldConfig::SOURCE_STATIC]);
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertInstanceOf(MediaEntity::class, $videoStruct->getMedia());
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
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
        $slot->setType('video');
        $slot->setConfig(['mediaId' => 'media123']);
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertNull($videoStruct->getMedia());
    }

    public function testEnrichWithDefaultConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $this->publicFilesystem->write('/bundles/frontend/assets/default/cms/contena.mp4', '');

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'bundles/frontend/assets/default/cms/contena.mp4'));

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        $media = $videoStruct->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('contena', $media->getFileName());
        static::assertSame('video/mp4', $media->getMimeType());
        static::assertSame('mp4', $media->getFileExtension());
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
        $slot->setType('video');
        $slot->setConfig(json_decode(json_encode($fieldConfig, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
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
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
    }

    public function testMediaWithMappedCustomFieldId(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $blog = new BlogEntity();
        $blog->setCustomFields(['heroVideo' => 'media123']);

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
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroVideo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
    }
}
