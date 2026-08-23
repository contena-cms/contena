<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class SeoUrlUpdateListenerTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    public function testChildCategorySeoUrlIndexing(): void
    {
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child2Id = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $categoryRepository = $this->getContainer()->get('category.repository');
        $categoryRepository->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'children' => [
                    [
                        'id' => $child1Id,
                        'name' => 'child1',
                    ],
                    [
                        'id' => $child2Id,
                        'name' => 'child2',
                    ],
                ],
            ],
        ], $context);

        // we don't check parent category here, because it's the home page and therefore has no seo url
        $criteria = new Criteria([$child1Id, $child2Id]);
        $criteria->addAssociation('seoUrls');
        $categories = $categoryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        // Check that SEO URLs are empty because the categories are not assigned to a channel yet.
        $child1 = $categories->get($child1Id);
        static::assertInstanceOf(CategoryEntity::class, $child1);
        $seoUrls = $child1->getSeoUrls();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
        static::assertCount(0, $seoUrls);

        $child2 = $categories->get($child2Id);
        static::assertInstanceOf(CategoryEntity::class, $child2);
        $seoUrls = $child2->getSeoUrls();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
        static::assertCount(0, $seoUrls);

        // Assign categories to a channel.
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test', categoryEntrypoint: $parentId);

        // update parent
        $update = [
            'id' => $parentId,
            'name' => 'updated',
        ];

        $categoryRepository->update([$update], Context::createDefaultContext());

        // we don't check parent category here, because it's the home page and therefore has no seo url
        $criteria = new Criteria([$child1Id, $child2Id]);
        $criteria->addAssociation('seoUrls');
        $categories = $categoryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $child1 = $categories->get($child1Id);
        static::assertInstanceOf(CategoryEntity::class, $child1);
        $seoUrls = $child1->getSeoUrls();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertSame('child1/', $seoUrl->getSeoPathInfo());

        $child2 = $categories->get($child2Id);
        static::assertInstanceOf(CategoryEntity::class, $child2);
        $seoUrls = $child2->getSeoUrls();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
        static::assertCount(1, $seoUrls);
        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertSame('child2/', $seoUrl->getSeoPathInfo());
    }
}
