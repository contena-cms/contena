<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\MainCategory;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class MainCategoryExtensionTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
    }

    public function testMainCategoryLoaded(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $ids = new IdsCollection();
        $categoryId = $ids->create('category');
        $this->categoryRepository->create([[
            'id' => $categoryId,
            'name' => 'Category',
        ]], Context::createDefaultContext());

        $id = $ids->create('blog');
        $blog = new BlogBuilder($ids, 'blog')
            ->visibility($channelId)
            ->mainCategory($channelId, 'category');
        $blogData = $blog->build();
        unset($blogData['mainCategories']);
        $this->blogRepository->create([[
            ...$blogData,
            'id' => $id,
        ]], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('mainCategories');

        /** @var BlogEntity $blog */
        $blog = $this->blogRepository->search($criteria, $channelContext->getContext())->getEntities()->first();

        static::assertNotNull($blog->getMainCategories());
        static::assertInstanceOf(BlogMainCategoryCollection::class, $blog->getMainCategories());
        static::assertEmpty($blog->getMainCategories());

        // update main category
        $categories = $this->categoryRepository->searchIds(new Criteria(), Context::createDefaultContext());

        $this->blogRepository->update([
            [
                'id' => $id,
                'mainCategories' => [
                    [
                        'channelId' => $channelId,
                        'categoryId' => $categories->firstId(),
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $blog = $this->blogRepository->search($criteria, $channelContext->getContext())
            ->getEntities()
            ->first();
        static::assertNotNull($blog);

        $mainCategories = $blog->getMainCategories();
        static::assertNotNull($mainCategories);
        static::assertInstanceOf(BlogMainCategoryCollection::class, $mainCategories);
        static::assertCount(1, $mainCategories);

        $mainCategory = $mainCategories->filterByChannelId($channelId)->first();
        static::assertInstanceOf(BlogMainCategoryEntity::class, $mainCategory);
        static::assertSame($channelId, $mainCategory->getChannelId());
        static::assertSame($categories->firstId(), $mainCategory->getCategoryId());
    }
}
