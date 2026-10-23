<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\SeoUrlRoute;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\Template\TemplateCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class SeoUrlUpdateListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;
    use FrontendChannelTestHelper;

    /**
     * @var EntityRepository<TemplateCollection>
     */
    private EntityRepository $templateRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateRepository = static::getContainer()->get('seo_url_template.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `channel`');
    }

    public function testDefaultNew(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog', 'blogNumber' => 'P1'], $channelId);

        $blog = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();
        static::assertNotNull($blog);
        $blogSeoUrls = $blog->getSeoUrls();
        static::assertNotNull($blogSeoUrls);
        $canonicalUrl = $blogSeoUrls->first();
        static::assertNotNull($canonicalUrl);
        static::assertSame('awesome-blog/P1', $canonicalUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($channelId, $id);

        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertNotNull($canonicalUrls->first());
        static::assertSame($canonicalUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testDefaultUpdateSamePath(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();

        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog', 'blogNumber' => 'P1'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog', 'description' => 'this description should not matter', 'blogNumber' => 'P1'], $channelId);

        $blog = $this->blogRepository->search(
            $this->getCriteria($id, $channelId),
            $channelContext->getContext()
        )
            ->getEntities()
            ->first();
        static::assertNotNull($blog);
        static::assertNotNull($blog->getSeoUrls());

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $blog->getSeoUrls()->first();
        static::assertNotNull($seoUrl);
        static::assertSame('awesome-blog/P1', $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($channelId, $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertNotNull($canonicalUrls->first());
        static::assertSame($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testDefaultUpdateDifferentPath(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();

        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog', 'blogNumber' => 'P1'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog v2', 'blogNumber' => 'P1'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog v3', 'blogNumber' => 'P1'], $channelId);

        $blog = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();
        static::assertNotNull($blog);
        static::assertNotNull($blog->getSeoUrls());

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $blog->getSeoUrls()->first();
        static::assertSame('awesome-blog-v3/P1', $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($channelId, $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', null);

        static::assertNotNull($canonicalUrls->first());
        static::assertSame($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(2, $nonCanonicals);
        static::assertCount(3, $seoUrls);
    }

    public function testCustomNew(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();
        $this->upsertTemplate([
            'id' => $id,
            'channelId' => $channelId,
            'template' => 'foo/{{ blog.name }}/bar',
        ]);

        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog'], $channelId);

        $first = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();
        static::assertNotNull($first);
        static::assertInstanceOf(SeoUrlCollection::class, $first->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getSeoUrls();
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $seoUrls->first();
        static::assertNotNull($seoUrl);
        static::assertSame($first->getId(), $seoUrl->getForeignKey());
        static::assertSame(BlogPageSeoUrlRoute::ROUTE_NAME, $seoUrl->getRouteName());
        static::assertSame('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertSame('foo/awesome-blog/bar', $seoUrl->getSeoPathInfo());
        static::assertTrue($seoUrl->getIsCanonical());
    }

    public function testCustomUpdateSamePath(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();
        $this->upsertTemplate([
            'id' => $id,
            'channelId' => $channelId,
            'template' => 'foo/{{ blog.name}}/bar',
        ]);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog', 'description' => 'should not matter'], $channelId);

        $first = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();
        static::assertNotNull($first);

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getSeoUrls();
        static::assertNotNull($seoUrls);
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $seoUrls->first();
        static::assertSame('foo/awesome-blog/bar', $seoUrl->getSeoPathInfo());
        static::assertTrue($seoUrl->getIsCanonical());

        $seoUrls = $this->getSeoUrls($channelId, $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', false);

        static::assertNotNull($seoUrl);
        static::assertNotNull($canonicalUrls->first());
        static::assertSame($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(0, $nonCanonicals);
        static::assertCount(1, $seoUrls);
    }

    public function testCustomUpdateDifferentPath(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();
        $this->upsertTemplate([
            'id' => $id,
            'channelId' => $channelId,
            'template' => 'foo/{{ blog.name }}/bar',
        ]);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog improved'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog improved again'], $channelId);

        $first = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();
        static::assertNotNull($first);
        static::assertNotNull($first->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getSeoUrls();
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $seoUrls->first();
        static::assertSame('foo/awesome-blog-improved-again/bar', $seoUrl->getSeoPathInfo());

        $seoUrls = $this->getSeoUrls($channelId, $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', null);

        static::assertNotNull($seoUrl);
        static::assertNotNull($canonicalUrls->first());
        static::assertSame($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(2, $nonCanonicals);
        static::assertCount(3, $seoUrls);
    }

    public function testUpdateWithUpdatedTemplate(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();
        $this->upsertTemplate([
            'id' => $id,
            'channelId' => $channelId,
            'template' => 'foo/{{ blog.name }}/bar',
        ]);

        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog'], $channelId);
        $this->upsertTemplate([
            'id' => $id,
            'channelId' => $channelId,
            'template' => 'bar/{{ blog.name }}/baz',
        ]);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog improved'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog improved'], $channelId);

        $first = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();

        static::assertNotNull($first);
        static::assertNotNull($first->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getSeoUrls();
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $seoUrls->first();
        static::assertSame($first->getId(), $seoUrl->getForeignKey());
        static::assertSame(BlogPageSeoUrlRoute::ROUTE_NAME, $seoUrl->getRouteName());
        static::assertSame('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertSame('bar/awesome-blog-improved/baz', $seoUrl->getSeoPathInfo());
        static::assertTrue($seoUrl->getIsCanonical());

        $seoUrls = $this->getSeoUrls($channelId, $id);
        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        $nonCanonicals = $seoUrls->filterByProperty('isCanonical', null);

        static::assertNotNull($seoUrl);
        static::assertNotNull($canonicalUrls->first());
        static::assertSame($seoUrl->getId(), $canonicalUrls->first()->getId());

        static::assertCount(1, $canonicalUrls);
        static::assertCount(1, $nonCanonicals);
        static::assertCount(2, $seoUrls);

        static::assertNotNull($nonCanonicals->first());
        static::assertSame('foo/awesome-blog/bar', $nonCanonicals->first()->getSeoPathInfo());
    }

    public function testIsMarkedAsDeleted(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = Uuid::randomHex();
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog', 'blogNumber' => 'P1'], $channelId);
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog v2', 'blogNumber' => 'P1'], $channelId);

        $blog = $this->blogRepository->search($this->getCriteria($id, $channelId), $channelContext->getContext())
            ->getEntities()
            ->first();

        static::assertNotNull($blog);
        static::assertNotNull($blog->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $blog->getSeoUrls();
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $seoUrls->first();
        static::assertSame('awesome-blog-v2/P1', $seoUrl->getSeoPathInfo());
        static::assertFalse($seoUrl->getIsDeleted());

        $this->blogRepository->delete([['id' => $id]], $channelContext->getContext());

        $seoUrls = $this->getSeoUrls($channelId, $id);
        static::assertCount(2, $seoUrls);
        static::assertCount(2, $seoUrls->filterByProperty('isDeleted', true));
        static::assertCount(0, $seoUrls->filterByProperty('isDeleted', false));
    }

    public function testAutoSlugify(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'template' => '{{ blog.name }}', // no slugify!
        ]);

        $id = Uuid::randomHex();
        $this->upsertBlog(['id' => $id, 'name' => 'foo bar'], $channelId);

        $context = $channelContext->getContext();

        $blog = $this->blogRepository->search($this->getCriteria($id, $channelId), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($blog);
        static::assertNotNull($blog->getSeoUrls());
        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $blog->getSeoUrls()->first();
        static::assertNotNull($seoUrl);
        static::assertSame('foo-bar', $seoUrl->getSeoPathInfo());
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testEntityIsSkippedOnRuntimeError(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'template' => '{{ blog.customFields.foo }}', // this throws a runtime error, because attributes is null
        ]);

        $id = Uuid::randomHex();
        $this->upsertBlog([
            'id' => $id,
            'name' => 'foo',
        ], $channelId);

        $context = $channelContext->getContext();

        $criteria = (new Criteria([$id]))->addAssociation('seoUrls');
        $blog = $this->blogRepository->search($criteria, $context)
            ->getEntities()
            ->first();
        static::assertNotNull($blog);
        static::assertNotNull($blog->getSeoUrls());

        $seoUrls = $blog->getSeoUrls()->filterByChannelId($channelId);
        static::assertCount(0, $seoUrls);

        $this->upsertBlog([
            'id' => $id,
            'name' => 'foo',
            'customFields' => [
                'foo' => 'bar',
            ],
        ], $channelId);

        $criteria = (new Criteria([$id]))->addAssociation('seoUrls');
        $blog = $this->blogRepository->search($criteria, $context)
            ->getEntities()
            ->first();
        static::assertNotNull($blog);
        static::assertNotNull($blog->getSeoUrls());
        $seoUrls = $blog->getSeoUrls()->filterByChannelId($channelId);

        static::assertNotCount(0, $seoUrls);
    }

    public function testMultiCreate(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $blogs = [
            [
                'id' => $id1,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'foo',
                'blogNumber' => 'P1',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
                'visibilities' => [
                    [
                        'channelId' => $channelId,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $id2,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand 2',
                ],
                'name' => 'bar',
                'blogNumber' => 'P2',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
                'visibilities' => [
                    [
                        'channelId' => $channelId,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ];
        $this->blogRepository->upsert($blogs, Context::createDefaultContext());

        $criteria = new Criteria([$id1, $id2]);
        $criteria->addAssociation('seoUrls');
        $blogs = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var BlogEntity $first */
        $first = $blogs->first();
        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $first->getSeoUrls();
        static::assertCount(1, $seoUrls);

        /** @var BlogEntity $last */
        $last = $blogs->last();
        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $last->getSeoUrls();
        static::assertCount(1, $seoUrls);
    }

    public function testInheritance(): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child2Id = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'foo',
                'blogNumber' => 'P1',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
                'visibilities' => [
                    [
                        'channelId' => $channelId,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $child1Id,
                'parentId' => $parentId,
                'blogNumber' => 'C1',
                'stock' => 0,
            ],
            [
                'id' => $child2Id,
                'parentId' => $parentId,
                'blogNumber' => 'C2',
                'stock' => 0,
            ],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->upsert($blogs, $context);

        $this->runWorker();

        // update parent
        $update = [
            'id' => $parentId,
            'name' => 'updated',
        ];

        $this->blogRepository->update([$update], Context::createDefaultContext());
        $this->runWorker();

        $this->runWorker();

        $criteria = new Criteria([$parentId, $child1Id, $child2Id]);
        $criteria->addAssociation('seoUrls');
        $blogs = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var BlogEntity $parent */
        $parent = $blogs->get($parentId);
        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $parent->getSeoUrls();
        static::assertCount(2, $seoUrls);
        /** @var SeoUrlEntity $canonical */
        $canonical = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canonical);
        static::assertSame('updated/P1', $canonical->getSeoPathInfo());

        /** @var BlogEntity $child1 */
        $child1 = $blogs->get($child1Id);
        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $child1->getSeoUrls();
        static::assertCount(2, $seoUrls);
        $canonical = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canonical);
        static::assertSame('updated/C1', $canonical->getSeoPathInfo());

        /** @var BlogEntity $child2 */
        $child2 = $blogs->get($child2Id);
        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $child2->getSeoUrls();
        static::assertCount(2, $seoUrls);
        $canonical = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canonical);
        static::assertSame('updated/C2', $canonical->getSeoPathInfo());
    }

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

        // check that seo Urls are empty, as the categories are not assigned to a sales channel yet
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

        // assign categories to sales channel
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

    public function testIndex(?int $seoUrlCount = 1): void
    {
        $channelId = Uuid::randomHex();
        $this->createFrontendChannelContext($channelId, 'test');

        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $writer = static::getContainer()->get(EntityWriter::class);

        $id = Uuid::randomHex();
        $blogs = [
            [
                'id' => $id,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'foo',
                'blogNumber' => 'P1',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
                'visibilities' => [
                    [
                        'channelId' => $channelId,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ];

        // the writer does not fire events, so seo urls are not created automatically
        $writer->insert($blogDefinition, $blogs, WriteContext::createFromContext(Context::createDefaultContext()));

        // Builds the index for visibilities
        static::getContainer()->get(InheritanceUpdater::class)->update('blog', [$id], Context::createDefaultContext());

        static::getContainer()
            ->get(SeoUrlUpdater::class)
            ->update(BlogPageSeoUrlRoute::ROUTE_NAME, [$id]);

        /** @var EntityRepository<BlogCollection> $blogRepo */
        $blogRepo = static::getContainer()->get('blog.repository');

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var BlogEntity $blog */
        $blog = $blogRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $blog->getSeoUrls();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);
        /** @var int $seoUrlCount */
        static::assertCount($seoUrlCount, $seoUrls);
    }

    public function testIndexWithEmptySeoUrlTemplate(): void
    {
        /** @var EntityRepository<SeoUrlTemplateCollection> $templateRepository */
        $templateRepository = static::getContainer()->get('seo_url_template.repository');

        $ids = $templateRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        $templates = $templateRepository->search(new Criteria($ids), Context::createDefaultContext())->getEntities();

        foreach ($templates as $template) {
            $template->setTemplate('');
        }

        $data = array_map(static fn (string $templateId): array => [
            'id' => $templateId,
            'template' => null,
        ], $templates->getIds());

        $templateRepository->upsert(array_values($data), Context::createDefaultContext());

        $this->testIndex(0);
    }

    public function testVariationTemplate(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $this->upsertTemplate([
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'template' => '{% if blog.variation %}{% for v in blog.variation %}/{{ v.option }}{% endfor %}{% endif %}',
        ]);

        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child2Id = Uuid::randomHex();

        $colorGroupId = Uuid::randomHex();
        $redOptionId = Uuid::randomHex();
        $greenOptionId = Uuid::randomHex();

        $sizeGroupId = Uuid::randomHex();
        $xlOptionId = Uuid::randomHex();
        $lOptionId = Uuid::randomHex();

        $blogs = [
            [
                'id' => $parentId,
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'amazing brand',
                ],
                'name' => 'foo',
                'blogNumber' => 'P1',
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                'stock' => 0,
                'properties' => [
                    [
                        'id' => $redOptionId,
                        'name' => 'red',
                        'colorHexCode' => '#ff0000',
                        'group' => [
                            'id' => $colorGroupId,
                            'name' => 'color',
                            'displayType' => 'color',
                            'position' => 0,
                        ],
                    ],
                    [
                        'id' => $greenOptionId,
                        'name' => 'green',
                        'colorHexCode' => '#00ff00',
                        'groupId' => $colorGroupId,
                    ],
                    [
                        'id' => $xlOptionId,
                        'name' => 'XL',
                        'group' => [
                            'id' => $sizeGroupId,
                            'name' => 'Size',
                            'displayType' => 'text',
                            'position' => 1,
                        ],
                    ],
                    [
                        'id' => $lOptionId,
                        'name' => 'L',
                        'groupId' => $sizeGroupId,
                    ],
                ],
                'configuratorSettings' => [
                    [
                        'id' => Uuid::randomHex(),
                        'optionId' => $redOptionId,
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'optionId' => $greenOptionId,
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'optionId' => $xlOptionId,
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'optionId' => $lOptionId,
                    ],
                ],
                'visibilities' => [
                    [
                        'channelId' => $channelId,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $child2Id,
                'parentId' => $parentId,
                'blogNumber' => 'C1',
                'stock' => 0,
                'options' => [
                    ['id' => $redOptionId],
                    ['id' => $lOptionId],
                ],
            ],
            [
                'id' => $child1Id,
                'parentId' => $parentId,
                'blogNumber' => 'C2',
                'stock' => 0,
                'options' => [
                    ['id' => $greenOptionId],
                    ['id' => $xlOptionId],
                ],
            ],
        ];

        $this->blogRepository->upsert($blogs, $channelContext->getContext());

        $parentSeoUrl = $this->getSeoUrls($channelId, $parentId)->first();
        static::assertNull($parentSeoUrl);

        $child1SeoUrl = $this->getSeoUrls($channelId, $child1Id)->first();
        static::assertNotNull($child1SeoUrl);
        static::assertSame('green/XL', $child1SeoUrl->getSeoPathInfo());

        $child2SeoUrl = $this->getSeoUrls($channelId, $child2Id)->first();
        static::assertNotNull($child2SeoUrl);
        static::assertSame('red/L', $child2SeoUrl->getSeoPathInfo());
    }

    private function getSeoUrls(string $channelId, string $blogId): SeoUrlCollection
    {
        /** @var EntityRepository<SeoUrlCollection> $repo */
        $repo = static::getContainer()->get('seo_url.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('channelId', $channelId));
        $criteria->addFilter(new EqualsFilter('foreignKey', $blogId));

        /** @var SeoUrlCollection $seoUrlCollection */
        $seoUrlCollection = $repo->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertNotNull($seoUrlCollection);

        return $seoUrlCollection;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertTemplate(array $data): void
    {
        $seoUrlTemplateDefaults = [
            'channelId' => TestDefaults::CHANNEL,
            'entityName' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
            'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
        ];
        $seoUrlTemplate = array_merge($seoUrlTemplateDefaults, $data);
        $this->templateRepository->upsert([$seoUrlTemplate], Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertBlog(array $data, string $channelId): void
    {
        $defaults = [
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'blogNumber' => Uuid::randomHex(),
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
            'stock' => 0,
            'visibilities' => [
                [
                    'channelId' => $channelId,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $data = array_merge($defaults, $data);

        try {
            $this->blogRepository->create([$data], Context::createDefaultContext());
        } catch (WriteTypeIntendException) {
            unset($data['visibilities']);
            $this->blogRepository->upsert([$data], Context::createDefaultContext());
        }
    }

    private function getCriteria(string $blogId, ?string $channelId = null, string $languageId = Defaults::LANGUAGE_SYSTEM): Criteria
    {
        $criteria = new Criteria([$blogId]);
        $seoUrlCriteria = $criteria->getAssociation('seoUrls');
        $seoUrlCriteria->addFilter(new EqualsFilter('isCanonical', true));
        $seoUrlCriteria->addFilter(new EqualsFilter('languageId', $languageId));
        $seoUrlCriteria->addFilter(new EqualsFilter('channelId', $channelId));

        return $criteria;
    }
}
