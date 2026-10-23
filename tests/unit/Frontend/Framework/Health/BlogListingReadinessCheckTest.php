<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogListingReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainCollection;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainContextFactory;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Contena\Frontend\Framework\SystemCheck\Util\FrontendHealthCheckResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(BlogListingReadinessCheck::class)]
class BlogListingReadinessCheckTest extends TestCase
{
    private Connection&Stub $connection;

    private ChannelDomainUtil&Stub $util;

    private AbstractChannelDomainProvider&Stub $domainProvider;

    private ChannelDomainContextFactory&Stub $contextFactory;

    private IdsCollection $ids;

    /**
     * @var list<string>
     */
    private array $requestedNavigationIds = [];

    private int $handledRequests = 0;

    /**
     * @var list<string>
     */
    private array $domainsWithoutContext = [];

    /**
     * @var list<ChannelDomain>
     */
    private array $contextDomains = [];

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->domainProvider = static::createStub(AbstractChannelDomainProvider::class);
        $this->ids = new IdsCollection();

        $this->initUtilMock();
        $this->initContextFactoryMock();
    }

    public function testName(): void
    {
        $check = $this->createCheck();
        static::assertSame('BlogListingReadiness', $check->name());
    }

    public function testCategory(): void
    {
        $check = $this->createCheck();
        static::assertSame('FEATURE', $check->category()->name);
    }

    public function testAllowedToRunIn(): void
    {
        $check = $this->createCheck();
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
    }

    public function testRunSuccessfully(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);

        $check = $this->createCheck($this->visibleCategoryResults());
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('BlogListingReadiness', $result->name);
        static::assertSame('Blog listing pages are OK for provided sales channels.', $result->message);
        static::assertSame('OK', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(200, $result->extra[0]['responseCode']);
        static::assertSame(200, $result->extra[1]['responseCode']);
    }

    public function testRunSkipped(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->initDomainMocks();
        $this->initCreateEmptyResult();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('BlogListingReadiness', $result->name);
        static::assertSame('No sales channels with blog listing pages found.', $result->message);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertCount(0, $result->extra);
    }

    public function testRunFailed(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_INTERNAL_SERVER_ERROR);

        $check = $this->createCheck($this->visibleCategoryResults());
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame('BlogListingReadiness', $result->name);
        static::assertSame('Some or all blog listing pages are unhealthy.', $result->message);
        static::assertSame('FAILURE', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(500, $result->extra[0]['responseCode']);
        static::assertSame(500, $result->extra[1]['responseCode']);
    }

    public function testChannelsWithoutVisibleCategoryAreSkipped(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        // no candidate category can be rendered, e.g. because all of them are restricted to a rule
        // that does not match for an anonymous visitor
        $check = $this->createCheck([[], []]);
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertSame(0, $this->handledRequests);
    }

    public function testRestrictedCategoryFallsBackToTheNextCandidate(): void
    {
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initDomainMocks();

        $this->connection->method('fetchAllAssociative')->willReturn([
            ['channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('restricted-category'), 'is_navigation_category' => 0],
            ['channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('visible-category'), 'is_navigation_category' => 0],
        ]);

        $check = $this->createCheck([$this->categories(['visible-category' => true])]);
        $result = $check->run();

        static::assertSame('OK', $result->status->name);
        static::assertSame([$this->ids->get('visible-category')], $this->requestedNavigationIds);
    }

    /**
     * Dynamic Access does not filter the criteria for categories, it deactivates restricted categories in a
     * `channel.category.loaded` listener. The frontend refuses to render an inactive category, so the
     * check has to fall back to the next candidate instead of probing it.
     */
    public function testCategoryDeactivatedOnLoadFallsBackToTheNextCandidate(): void
    {
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initDomainMocks();

        $this->connection->method('fetchAllAssociative')->willReturn([
            ['channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('restricted-category'), 'is_navigation_category' => 0],
            ['channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('visible-category'), 'is_navigation_category' => 0],
        ]);

        $check = $this->createCheck([$this->categories(['restricted-category' => false, 'visible-category' => true])]);
        $result = $check->run();

        static::assertSame('OK', $result->status->name);
        static::assertSame([$this->ids->get('visible-category')], $this->requestedNavigationIds);
    }

    /**
     * A domain whose context cannot be created, e.g. because its currency is no longer assigned to the sales
     * channel, is that sales channel's failure. The other sales channels are still probed and reported.
     */
    public function testADomainWithoutContextFailsOnItsOwn(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->domainsWithoutContext = [$this->ids->get('domain-sales-channel-1')];

        $result = $this->createCheck([$this->categories(['category-2' => true])])->run();

        static::assertSame(Status::ERROR, $result->status);
        static::assertFalse($result->healthy);
        static::assertCount(2, $result->extra);
        static::assertSame(Response::HTTP_BAD_REQUEST, $result->extra[0]['responseCode']);
        static::assertSame('no context for this domain', $result->extra[0]['errorMessage']);
        static::assertSame(Response::HTTP_OK, $result->extra[1]['responseCode']);
        static::assertSame([$this->ids->get('category-2')], $this->requestedNavigationIds);
    }

    public function testCategoriesAreResolvedByTheCandidatesOfTheChannel(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $criteria = null;
        $this->createCheck([
            function (Criteria $actual) use (&$criteria) {
                $criteria = $actual;

                return [];
            },
            [],
        ])->run();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame([$this->ids->get('category-1')], $criteria->getIds());
    }

    /**
     * The URL that is probed belongs to one domain, and the context of that domain feeds the criteria
     * processing that decides which categories are visible. Looking the category up with any other context
     * would evaluate a restriction against a different context than the request that follows.
     */
    #[TestDox('The lookup context describes the domain whose URL is probed, not the sales channel defaults')]
    public function testTheLookupContextIsBuiltForTheProbedDomain(): void
    {
        $this->initDataMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $this->createCheck([[], []])->run();

        // the third sales channel has no candidate category, so no context is needed for it
        static::assertSame([
            $this->ids->get('domain-sales-channel-1'),
            $this->ids->get('domain-sales-channel-2'),
        ], array_map(static fn (ChannelDomain $domain) => $domain->id, $this->contextDomains));
    }

    /**
     * @param array<callable(Criteria, ChannelContext): (CategoryCollection|array{})|CategoryCollection|array{}> $searchResults
     */
    private function createCheck(array $searchResults = []): BlogListingReadinessCheck
    {
        /** @var StaticChannelRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = new StaticChannelRepository($searchResults);

        return new BlogListingReadinessCheck(
            $this->util,
            $this->connection,
            $this->domainProvider,
            $categoryRepository,
            $this->contextFactory
        );
    }

    /**
     * @return list<CategoryCollection>
     */
    private function visibleCategoryResults(): array
    {
        return [
            $this->categories(['category-1' => true]),
            $this->categories(['category-2' => true]),
        ];
    }

    /**
     * @param array<string, bool> $activeByKey
     */
    private function categories(array $activeByKey): CategoryCollection
    {
        $categories = new CategoryCollection();
        foreach ($activeByKey as $key => $active) {
            $category = new CategoryEntity();
            $category->setId($this->ids->get($key));
            $category->setActive($active);
            $categories->add($category);
        }

        return $categories;
    }

    private function initUtilMock(): void
    {
        $this->util = static::createStub(ChannelDomainUtil::class);
        $this->util->method('runAsChannelRequest')
            ->willReturnCallback(static function (callable $callback): mixed {
                return $callback();
            });

        $this->util->method('runWhileTrustingAllHosts')
            ->willReturnCallback(static function (callable $callback): mixed {
                return $callback();
            });

        $this->util->method('createExceptionResult')->willReturnCallback(
            static fn (string $url, \Throwable $e): FrontendHealthCheckResult => FrontendHealthCheckResult::create($url, Response::HTTP_BAD_REQUEST, 0.0, $e->getMessage())
        );

        $this->util->method('generateDomainUrl')->willReturnCallback(
            function (string $domain, string $routeName, array $parameters = []): string {
                $this->requestedNavigationIds[] = (string) ($parameters['navigationId'] ?? '');

                return $domain . $routeName;
            }
        );
    }

    private function initHandleRequest(int $responseCode): void
    {
        $this->util->method('handleRequest')->willReturnCallback(
            function () use ($responseCode): FrontendHealthCheckResult {
                ++$this->handledRequests;

                return FrontendHealthCheckResult::create(
                    'http://localhost:8000/blogs',
                    $responseCode,
                    1.23
                );
            }
        );
    }

    private function initContextFactoryMock(): void
    {
        $this->contextFactory = static::createStub(ChannelDomainContextFactory::class);
        $this->contextFactory->method('create')->willReturnCallback(
            function (ChannelDomain $domain): ChannelContext {
                if (\in_array($domain->id, $this->domainsWithoutContext, true)) {
                    throw new \Error('no context for this domain');
                }

                $this->contextDomains[] = $domain;

                return Generator::generateChannelContext();
            }
        );
    }

    private function initDataMocks(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['channel_id' => $this->ids->get('sales-channel-1'), 'category_id' => $this->ids->get('category-1'), 'is_navigation_category' => 0],
            ['channel_id' => $this->ids->get('sales-channel-2'), 'category_id' => $this->ids->get('category-2'), 'is_navigation_category' => 0],
        ]);

        $this->initDomainMocks();
    }

    private function initDomainMocks(): void
    {
        $collection = new ChannelDomainCollection([
            $this->domain('sales-channel-1', 'http://localhost:8000/de'),
            $this->domain('sales-channel-2', 'http://localhost:8000/en'),
            $this->domain('sales-channel-3', 'http://localhost:8000/invalid'),
        ]);

        $this->domainProvider->method('fetchChannelDomains')->willReturn($collection);
    }

    private function domain(string $key, string $url): ChannelDomain
    {
        return ChannelDomain::create(
            $this->ids->get($key),
            $url,
            $this->ids->get('domain-' . $key),
            $this->ids->get('language-' . $key),
            $this->ids->get('currency-' . $key),
        );
    }

    private function initCreateEmptyResult(): void
    {
        $this->util->method('createEmptyResult')
            ->willReturn(new Result(
                'BlogListingReadiness',
                Status::SKIPPED,
                'No sales channels with blog listing pages found.',
                true,
                []
            ));
    }
}
