<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogVisibilityTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $channelId1;

    private string $channelId2;

    private string $blogId1;

    private string $blogId2;

    private string $blogId3;

    private string $blogId4;

    private SearchPageLoader $searchPageLoader;

    private SuggestPageLoader $suggestPageLoader;

    private AbstractChannelContextFactory $contextFactory;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private BlogPageLoader $blogPageLoader;

    private string $categoryId;

    private SearchKeywordUpdater $searchKeywordUpdater;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchPageLoader = static::getContainer()->get(SearchPageLoader::class);
        $this->suggestPageLoader = static::getContainer()->get(SuggestPageLoader::class);
        $this->blogPageLoader = static::getContainer()->get(BlogPageLoader::class);

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->contextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $this->searchKeywordUpdater = static::getContainer()->get(SearchKeywordUpdater::class);
        $this->resetSearchKeywordUpdaterConfig();

        $this->ids = new IdsCollection();
        $this->insertData();
    }

    public function testVisibilityInListing(): void
    {
        $channelContext = $this->contextFactory->create(Uuid::randomHex(), $this->channelId1);

        $request = new Request();
        $request->attributes->set('_route_params', ['navigationId' => $this->categoryId]);

        $data = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryId, $request, $channelContext, new Criteria())
            ->getResult();

        static::assertSame(1, $data->getTotal());
        static::assertTrue($data->getEntities()->has($this->blogId3));

        $channelContext = $this->contextFactory->create(Uuid::randomHex(), $this->channelId2);

        $data = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryId, $request, $channelContext, new Criteria())
            ->getResult();

        static::assertSame(1, $data->getTotal());
        static::assertTrue($data->getEntities()->has($this->blogId1));
    }

    public function testVisibilityInSearch(): void
    {
        $channelContext = $this->contextFactory->create(Uuid::randomHex(), $this->channelId1);

        $request = new Request(['search' => 'test']);

        $page = $this->searchPageLoader->load($request, $channelContext);

        static::assertCount(2, $page->getListing()->getEntities());
        static::assertTrue($page->getListing()->getEntities()->has($this->blogId2));
        static::assertTrue($page->getListing()->getEntities()->has($this->blogId3));

        $channelContext = $this->contextFactory->create(Uuid::randomHex(), $this->channelId2);
        $page = $this->searchPageLoader->load($request, $channelContext);

        static::assertCount(2, $page->getListing()->getEntities());
        static::assertTrue($page->getListing()->getEntities()->has($this->blogId1));
        static::assertTrue($page->getListing()->getEntities()->has($this->blogId2));
    }

    public function testVisibilityOnBlogPage(): void
    {
        $cases = [
            ['channelId' => $this->channelId1, 'blogId' => $this->blogId1, 'visible' => false],
            ['channelId' => $this->channelId1, 'blogId' => $this->blogId2, 'visible' => true],
            ['channelId' => $this->channelId1, 'blogId' => $this->blogId3, 'visible' => true],
            ['channelId' => $this->channelId1, 'blogId' => $this->blogId4, 'visible' => true],
            ['channelId' => $this->channelId2, 'blogId' => $this->blogId1, 'visible' => true],
            ['channelId' => $this->channelId2, 'blogId' => $this->blogId2, 'visible' => true],
            ['channelId' => $this->channelId2, 'blogId' => $this->blogId3, 'visible' => true],
            ['channelId' => $this->channelId2, 'blogId' => $this->blogId4, 'visible' => false],
        ];

        foreach ($cases as $index => $case) {
            $channelContext = $this->contextFactory->create(Uuid::randomHex(), $case['channelId']);

            $request = new Request([], [], ['blogId' => $case['blogId']]);

            $e = null;
            $page = null;

            try {
                $page = $this->blogPageLoader->load($request, $channelContext);
            } catch (\Exception $e) {
            }

            if ($case['visible']) {
                static::assertNull($e, 'Exception should not be thrown.');
                static::assertNotNull($page, 'Page should not be null');
                static::assertSame($case['blogId'], $page->getBlog()->getId());

                continue;
            }

            if (!Feature::isActive('v6.8.0.0')) {
                static::assertInstanceOf(BlogNotFoundException::class, $e, 'case #' . $index);
            } else {
                static::assertInstanceOf(BlogException::class, $e, 'case #' . $index);
            }
        }
    }

    public function testVisibilityInSuggest(): void
    {
        $channelContext = $this->contextFactory->create(Uuid::randomHex(), $this->channelId1);

        $request = new Request(['search' => 'test']);

        $page = $this->suggestPageLoader->load($request, $channelContext);

        static::assertCount(2, $page->getSearchResult()->getEntities());
        static::assertTrue($page->getSearchResult()->getEntities()->has($this->blogId2));
        static::assertTrue($page->getSearchResult()->getEntities()->has($this->blogId3));

        $channelContext = $this->contextFactory->create(Uuid::randomHex(), $this->channelId2);
        $page = $this->searchPageLoader->load($request, $channelContext);

        static::assertCount(2, $page->getListing()->getEntities());
        static::assertTrue($page->getListing()->getEntities()->has($this->blogId1));
        static::assertTrue($page->getListing()->getEntities()->has($this->blogId2));
    }

    private function insertData(): void
    {
        $this->channelId1 = $this->createChannel('sales-1');
        $this->channelId2 = $this->createChannel('sales-2');
        $this->categoryId = $this->ids->get('category');
        $this->blogId1 = $this->ids->get('blog-1');
        $this->blogId2 = $this->ids->get('blog-2');
        $this->blogId3 = $this->ids->get('blog-3');
        $this->blogId4 = $this->ids->get('blog-4');

        $blogs = [
            $this->createBlog($this->blogId1, [
                $this->channelId2 => BlogVisibilityDefinition::VISIBILITY_ALL,
            ]),
            $this->createBlog($this->blogId2, [
                $this->channelId1 => BlogVisibilityDefinition::VISIBILITY_SEARCH,
                $this->channelId2 => BlogVisibilityDefinition::VISIBILITY_SEARCH,
            ]),
            $this->createBlog($this->blogId3, [
                $this->channelId1 => BlogVisibilityDefinition::VISIBILITY_ALL,
                $this->channelId2 => BlogVisibilityDefinition::VISIBILITY_LINK,
            ]),
            $this->createBlog($this->blogId4, [
                $this->channelId1 => BlogVisibilityDefinition::VISIBILITY_LINK,
            ]),
        ];

        $context = Context::createDefaultContext();

        $this->blogRepository->create($blogs, $context);
    }

    /**
     * @param array<string, int> $visibilities
     *
     * @return array<string, array<int<0, max>|string, array<string, bool|int|string>|int|string>|int|string>
     */
    private function createBlog(string $id, array $visibilities): array
    {
        $mapped = [];
        foreach ($visibilities as $channel => $visibility) {
            $mapped[] = ['channelId' => $channel, 'visibility' => $visibility];
        }

        return [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => $mapped,
            'categories' => [
                ['id' => $this->categoryId, 'name' => 'test'],
            ],
        ];
    }

    private function createChannel(string $key): string
    {
        $id = $this->ids->create($key);

        $snippetSetId = (string) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM snippet_set LIMIT 1');

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigation' => ['name' => 'test'],
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'url' => 'test.de/' . $id,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => Uuid::fromBytesToHex($snippetSetId),
                ],
            ],
        ];

        static::getContainer()->get('channel.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }

    private function resetSearchKeywordUpdaterConfig(): void
    {
        $this->searchKeywordUpdater->reset();
    }
}
