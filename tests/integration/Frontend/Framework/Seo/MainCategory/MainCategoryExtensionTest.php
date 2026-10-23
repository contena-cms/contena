<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\MainCategory;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Contena\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MainCategoryExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendChannelTestHelper;

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

        $id = $this->createTestBlog();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('mainCategories');

        /** @var BlogEntity $blog */
        $blog = $this->blogRepository->search($criteria, $channelContext->getContext())->getEntities()->first();

        static::assertNotNull($blog->getMainCategories());
        static::assertInstanceOf(MainCategoryCollection::class, $blog->getMainCategories());
        static::assertCount(0, $blog->getMainCategories());

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
        static::assertInstanceOf(MainCategoryCollection::class, $mainCategories);
        static::assertCount(1, $mainCategories);

        $mainCategory = $mainCategories->filterByChannelId($channelId)->first();
        static::assertInstanceOf(MainCategoryEntity::class, $mainCategory);
        static::assertSame($channelId, $mainCategory->getChannelId());
        static::assertSame($categories->firstId(), $mainCategory->getCategoryId());
    }

    private function createTestBlog(): string
    {
        $id = Uuid::randomHex();
        $payload = [
            'id' => $id,
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'blogNumber' => 'P1234',
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 12,
                    'linked' => false,
                ],
            ],
            'stock' => 0,
        ];

        $this->blogRepository->create([
            $payload,
        ], Context::createDefaultContext());

        return $id;
    }
}
