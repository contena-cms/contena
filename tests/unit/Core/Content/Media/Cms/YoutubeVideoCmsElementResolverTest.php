<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\ImageStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Media\Cms\YoutubeVideoCmsElementResolver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(YoutubeVideoCmsElementResolver::class)]
class YoutubeVideoCmsElementResolverTest extends TestCase
{
    public function testCollectReturnsNullWithMappedConfigAndResolverContextWithoutEntity(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
        ]));

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsNullWithMappedConfigResolvedToMediaEntity(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

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
        ]));

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectCreatesMediaCriteriaWithMappedStringId(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $blog = new BlogEntity();
        $blog->setCustomFields(['heroPreview' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
        ]));

        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $definitionData = $collection->all()[MediaDefinition::class] ?? null;
        static::assertIsArray($definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = $definitionData['media_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testEnrichMappedStringMediaSetsMediaIdAndMedia(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $blog = new BlogEntity();
        $blog->setCustomFields(['heroPreview' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
        ]));

        $media = new MediaEntity();
        $media->setId('media-1');

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new MediaCollection([$media]));

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $searchResult);

        $resolver->enrich($slot, $context, $data);

        $imageData = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageData);
        static::assertSame('media-1', $imageData->getMediaId());
        static::assertSame($media, $imageData->getMedia());
    }

    public function testEnrichMappedStringMediaSetsOnlyMediaIdWhenSearchResultMissing(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $blog = new BlogEntity();
        $blog->setCustomFields(['heroPreview' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateChannelContext(),
            new Request(),
            static::createStub(BlogDefinition::class),
            $blog,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
        ]));

        $resolver->enrich($slot, $context, new ElementDataCollection());

        $imageData = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageData);
        static::assertSame('media-1', $imageData->getMediaId());
        static::assertNull($imageData->getMedia());
    }
}
