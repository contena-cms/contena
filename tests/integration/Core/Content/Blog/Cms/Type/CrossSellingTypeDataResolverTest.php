<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\CrossSellingStruct;
use Contena\Core\Content\Blog\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\CrossSelling\AbstractBlogCrossSellingRoute;
use Contena\Core\Content\Blog\Channel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Content\Blog\Channel\CrossSelling\BlogCrossSellingRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CrossSellingTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CrossSellingCmsElementResolver $crossSellingResolver;

    protected function setUp(): void
    {
        $mock = static::createStub(AbstractBlogCrossSellingRoute::class);
        $mock->method('load')->willReturn(
            new BlogCrossSellingRouteResponse(
                new CrossSellingElementCollection()
            )
        );

        $this->crossSellingResolver = new CrossSellingCmsElementResolver($mock);
    }

    public function testType(): void
    {
        static::assertSame(CrossSellingCmsElementResolver::TYPE, $this->crossSellingResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(CrossSellingCmsElementResolver::TYPE);
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->crossSellingResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(CrossSellingCmsElementResolver::TYPE);
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->crossSellingResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(CrossSellingCmsElementResolver::TYPE);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->crossSellingResolver->enrich($slot, $resolverContext, $result);

        /** @var CrossSellingStruct|null $crossSellingStruct */
        $crossSellingStruct = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $crossSellingStruct);
        static::assertNull($crossSellingStruct->getCrossSellings());
    }

    public function testEnrichWithConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('blog_id', new EntitySearchResult(
            'blog',
            1,
            new BlogCollection(),
            null,
            new Criteria(),
            $resolverContext->getChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, 'blog123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(CrossSellingCmsElementResolver::TYPE);
        $slot->setFieldConfig($fieldConfig);

        $this->crossSellingResolver->enrich($slot, $resolverContext, $result);

        /** @var CrossSellingStruct|null $crossSellingStruct */
        $crossSellingStruct = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $crossSellingStruct);
    }

    public function testCollectWithEmptyBlogId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('blog', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(CrossSellingCmsElementResolver::TYPE);
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->crossSellingResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }
}
