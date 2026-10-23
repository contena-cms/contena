<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
use Contena\Core\Content\Media\Cms\YoutubeVideoCmsElementResolver;
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
#[CoversClass(YoutubeVideoCmsElementResolver::class)]
class YoutubeVideoTypeDataResolverTest extends TestCase
{
    private YoutubeVideoCmsElementResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new YoutubeVideoCmsElementResolver();
    }

    public function testType(): void
    {
        static::assertSame('youtube-video', $this->resolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->resolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithPreviewMediaId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->resolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123']);
        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedPreviewMediaCustomFieldId(): void
    {
        $blog = new BlogEntity();
        $blog->setCustomFields(['heroPreview' => 'media123']);

        $resolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroPreview'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->resolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);

        $expectedCriteria = new Criteria(['media123']);
        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testEnrichWithMappedPreviewMediaCustomFieldId(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $blog = new BlogEntity();
        $blog->setCustomFields(['heroPreview' => 'media123']);

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
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroPreview'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertNull($imageStruct->getMediaId());
        static::assertNull($imageStruct->getMedia());
    }

    public function testEnrichWithPreviewMediaOnly(): void
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
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithMappedPreviewMediaEntity(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $blogMedia = new BlogMediaEntity();
        $blogMedia->setMedia($media);

        $blog = new BlogEntity();
        $blog->setCover($blogMedia);

        $resolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'cover.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }
}
