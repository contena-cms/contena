<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\ImageSliderStruct;
use Contena\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Contena\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ImageSliderTypeDataResolver::class)]
class ImageSliderTypeDataResolverTest extends TestCase
{
    public function testCollectCreatesBlogMediaCriteriaIfMappedMediaIsNotResolved(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'),
        ]));

        $context = $this->createEntityResolverContext($this->createBlogMediaCollectionWithoutMediaAssociation());

        $criteriaCollection = $resolver->collect($slot, $context);

        static::assertNotNull($criteriaCollection);
        static::assertArrayHasKey(BlogMediaDefinition::class, $criteriaCollection->all());
        static::assertArrayHasKey('blog_media_slot-id', $criteriaCollection->all()[BlogMediaDefinition::class]);
    }

    public function testCollectSkipsBlogMediaCriteriaIfMappedMediaIsResolved(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'),
        ]));

        $context = $this->createEntityResolverContext($this->createBlogMediaCollectionWithMediaAssociation());

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectWithStaticConfigStillCreatesMediaCriteria(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $context = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, [['mediaId' => 'media-1']]),
        ]));

        $criteriaCollection = $resolver->collect($slot, $context);

        static::assertNotNull($criteriaCollection);
        static::assertArrayHasKey(MediaDefinition::class, $criteriaCollection->all());
        static::assertArrayHasKey('media_slot-id', $criteriaCollection->all()[MediaDefinition::class]);
    }

    public function testEnrichUsesMappedBlogMediaResult(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $context = $this->createEntityResolverContext(new BlogMediaCollection());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'blog.media'),
        ]));

        $result = new ElementDataCollection();
        $result->add('blog_media_slot-id', new EntitySearchResult(
            'blog_media',
            2,
            $this->createBlogMediaCollectionWithMediaAssociation(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $resolver->enrich($slot, $context, $result);

        /** @var ImageSliderStruct $data */
        $data = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $data);
        $sliderItems = $data->getSliderItems();
        static::assertIsArray($sliderItems);
        static::assertCount(2, $sliderItems);
    }

    private function createEntityResolverContext(BlogMediaCollection $blogMedia): EntityResolverContext
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog-1');
        $blog->setMedia($blogMedia);

        return new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::createStub(EntityDefinition::class),
            $blog
        );
    }

    private function createBlogMediaCollectionWithMediaAssociation(): BlogMediaCollection
    {
        $item = new BlogMediaEntity();
        $item->setId('pm-1');
        $item->setMediaId('media-1');
        $item->setMedia($this->createMediaEntity('media-1'));

        $item2 = new BlogMediaEntity();
        $item2->setId('pm-2');
        $item2->setMediaId('media-2');
        $item2->setMedia($this->createMediaEntity('media-2'));

        return new BlogMediaCollection([$item, $item2]);
    }

    private function createBlogMediaCollectionWithoutMediaAssociation(): BlogMediaCollection
    {
        $item = new BlogMediaEntity();
        $item->setId('pm-1');
        $item->setMediaId('media-1');

        return new BlogMediaCollection([$item]);
    }

    private function createMediaEntity(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
