<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\ManufacturerLogoStruct;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Cms\ManufacturerLogoCmsElementResolver;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ManufacturerLogoTypeCmsResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ManufacturerLogoCmsElementResolver $manufacturerLogoCmsElementResolver;

    protected function setUp(): void
    {
        $this->manufacturerLogoCmsElementResolver = static::getContainer()->get(ManufacturerLogoCmsElementResolver::class);
    }

    public function testType(): void
    {
        static::assertSame('manufacturer-logo', $this->manufacturerLogoCmsElementResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $collection = $this->manufacturerLogoCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $this->manufacturerLogoCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ManufacturerLogoStruct|null $manufacturerLogoStruct */
        $manufacturerLogoStruct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $manufacturerLogoStruct);
        static::assertNull($manufacturerLogoStruct->getManufacturer());
    }

    public function testEnrichEntityResolverContext(): void
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer_01');
        $blog = new ChannelBlogEntity();
        $blog->setId('blog_01');
        $blog->setManufacturer($manufacturer);
        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::getContainer()->get(ChannelBlogDefinition::class), $blog);
        $result = new ElementDataCollection();

        $media = new MediaEntity();
        $media->setId('media_01');

        $result->add('media_id', new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media_01'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');
        $slot->setFieldConfig($fieldConfig);

        $this->manufacturerLogoCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ManufacturerLogoStruct|null $manufacturerLogoStruct */
        $manufacturerLogoStruct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $manufacturerLogoStruct);
        static::assertNotNull($manufacturerLogoStruct->getManufacturer());
        static::assertSame('manufacturer_01', $manufacturerLogoStruct->getManufacturer()->getId());
        static::assertSame('media_01', $manufacturerLogoStruct->getMediaId());
    }

    public function testCollectWithMappedManufacturerWithoutMediaInEntityContext(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog_01');
        $blog->setManufacturerId('manufacturer_01');
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer_01');
        $blog->setManufacturer($manufacturer);

        $resolverContext = new EntityResolverContext(
            static::createStub(ChannelContext::class),
            new Request(),
            static::getContainer()->get(ChannelBlogDefinition::class),
            $blog
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'blog.manufacturer.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->manufacturerLogoCmsElementResolver->collect($slot, $resolverContext);
        static::assertNotNull($collection);
        static::assertArrayHasKey(BlogManufacturerDefinition::class, $collection->all());

        $criteria = $collection->all()[BlogManufacturerDefinition::class]['mapped_product_manufacturer_id'];
        static::assertSame(['manufacturer_01'], $criteria->getIds());
        static::assertArrayHasKey('media', $criteria->getAssociations());
    }
}
