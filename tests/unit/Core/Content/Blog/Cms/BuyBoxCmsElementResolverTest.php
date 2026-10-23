<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\BuyBoxStruct;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Cms\BuyBoxCmsElementResolver;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BuyBoxCmsElementResolver::class)]
class BuyBoxCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        /** @var StaticEntityRepository<BlogReviewCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new BuyBoxCmsElementResolver(
            static::createStub(BlogConfiguratorLoader::class),
            $repository,
        );

        static::assertSame('buy-box', $resolver->getType());
    }

    public function testEnrichBuyBox(): void
    {
        $configurationLoader = static::createStub(BlogConfiguratorLoader::class);
        /** @var EntityRepository<BlogReviewCollection>&MockObject */
        $repository = static::createStub(EntityRepository::class);
        $repository->method('aggregate')->willReturn(new AggregationResultCollection());

        $resolver = new BuyBoxCmsElementResolver($configurationLoader, $repository);

        $blogId = 'blog-1';
        $config = new FieldConfigCollection([new FieldConfig('blog', FieldConfig::SOURCE_STATIC, $blogId)]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelBlogCollection([$blog]));

        $data = new ElementDataCollection();
        $data->add('blog_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $data);

        $blog = $data->getBlog();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame('blog-1', $blog->getId());
    }

    public function testEnrichSetsEmptyBuyBoxWithoutConfig(): void
    {
        $configurationLoader = static::createStub(BlogConfiguratorLoader::class);

        /** @var StaticEntityRepository<BlogReviewCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new BuyBoxCmsElementResolver($configurationLoader, $repository);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $data);
        static::assertNull($data->getBlog());
    }
}
