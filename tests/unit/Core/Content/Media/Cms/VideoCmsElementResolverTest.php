<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\VideoStruct;
use Contena\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Contena\Core\Content\Media\Cms\VideoCmsElementResolver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(VideoCmsElementResolver::class)]
class VideoCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        static::assertSame('video', $resolver->getType());
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
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

    public function testCollectReturnsNullWithMappedConfig(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsNullWithMappedConfigResolvedToMediaEntity(): void
    {
        $media = new MediaEntity();
        $media->setId('media-1');

        $blogMedia = new BlogMediaEntity();
        $blogMedia->setMedia($media);

        $blog = new BlogEntity();
        $blog->setCover($blogMedia);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'cover.media'),
        ]));

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectCreatesMediaCriteriaWithMappedStringId(): void
    {
        $blog = new BlogEntity();
        $blog->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroVideo'),
        ]));

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $definitionData = $collection->all()[MediaDefinition::class] ?? null;
        static::assertIsArray($definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = $definitionData['media_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testEnrichStaticSlotWithMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $data);
        static::assertSame('media-1', $data->getMediaId());

        $media = $data->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media-1', $media->getId());
    }

    public function testEnrichWithDefaultMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'bundles/frontend/assets/default/cms/contena.mp4'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $defaultMedia = new MediaEntity();
        $defaultMedia->setId('default-1');

        $mediaResolver = static::createStub(AbstractDefaultMediaResolver::class);
        $mediaResolver->method('getDefaultCmsMediaEntity')->willReturn($defaultMedia);

        $resolver = new VideoCmsElementResolver($mediaResolver);
        $resolver->enrich($slot, $context, new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $data);
        static::assertSame($defaultMedia, $data->getMedia());
        static::assertNull($data->getMediaId());
    }

    public function testEnrichWithAriaLabel(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
            new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, 'Video description for accessibility'),
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('Video description for accessibility', $videoData->getAriaLabel());
    }

    public function testEnrichMappedStringMediaSetsMediaIdAndMedia(): void
    {
        $blog = new BlogEntity();
        $blog->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroVideo'),
        ]));

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new MediaCollection([$media]));

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('media-1', $videoData->getMediaId());
        static::assertSame($media, $videoData->getMedia());
    }

    public function testEnrichMappedStringMediaSetsOnlyMediaIdWhenSearchResultMissing(): void
    {
        $blog = new BlogEntity();
        $blog->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.customFields.heroVideo'),
        ]));

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, new ElementDataCollection());

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('media-1', $videoData->getMediaId());
        static::assertNull($videoData->getMedia());
    }
}
