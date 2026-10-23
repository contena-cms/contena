<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilter;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogDetailReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainCollection;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainContextFactory;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Contena\Frontend\Framework\SystemCheck\Util\FrontendHealthCheckResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(BlogDetailReadinessCheck::class)]
class BlogDetailReadinessCheckTest extends TestCase
{
    private ChannelDomainUtil&Stub $util;

    private AbstractChannelDomainProvider&Stub $domainProvider;

    private ChannelDomainContextFactory&Stub $contextFactory;

    private IdsCollection $ids;

    /**
     * @var list<ChannelDomain>
     */
    private array $contextDomains = [];

    private bool $hideCloseoutBlogs = true;

    /**
     * @var list<string>
     */
    private array $domainsWithoutContext = [];

    private int $handledRequests = 0;

    protected function setUp(): void
    {
        $this->domainProvider = static::createStub(ChannelDomainProvider::class);
        $this->ids = new IdsCollection();

        $this->initUtilMock();
        $this->initContextFactoryMock();
    }

    public function testName(): void
    {
        $check = $this->createCheck();
        static::assertSame('BlogDetailReadiness', $check->name());
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
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);

        $check = $this->createCheck($this->blogSearchResults());
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('BlogDetailReadiness', $result->name);
        static::assertSame('Blog detail pages are OK for provided sales channels.', $result->message);
        static::assertSame('OK', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(200, $result->extra[0]['responseCode']);
        static::assertSame(200, $result->extra[1]['responseCode']);
    }

    public function testRunSkipped(): void
    {
        $this->domainProvider->method('fetchChannelDomains')->willReturn(new ChannelDomainCollection([]));
        $this->initCreateEmptyResult();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('BlogDetailReadiness', $result->name);
        static::assertSame('No sales channels with blog detail pages found.', $result->message);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertCount(0, $result->extra);
    }

    public function testRunFailed(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_INTERNAL_SERVER_ERROR);

        $check = $this->createCheck($this->blogSearchResults());
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame('BlogDetailReadiness', $result->name);
        static::assertSame('Some or all blog detail pages are unhealthy.', $result->message);
        static::assertSame('FAILURE', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(500, $result->extra[0]['responseCode']);
        static::assertSame(500, $result->extra[1]['responseCode']);
    }

    public function testChannelsWithoutVisibleBlogAreSkipped(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        // no sales channel has a blog the frontend would render, e.g. because every blog is
        // restricted to a rule that does not match for an anonymous visitor
        $check = $this->createCheck([[], [], []]);
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertSame(0, $this->handledRequests);
    }

    public function testBlogIsResolvedWithFrontendVisibilityCriteria(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $criteria = null;
        $this->createCheck([
            function (Criteria $actual) use (&$criteria) {
                $criteria = $actual;

                return [];
            },
            [],
            [],
        ])->run();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(1, $criteria->getLimit());
        static::assertEquals([new BlogCloseoutFilter()], $criteria->getFilters());
        static::assertEquals([new FieldSorting('id')], $criteria->getSorting());

        // the BlogAvailableFilter must be added by ChannelBlogDefinition::processCriteria(),
        // otherwise the check no longer shares the visibility handling of the frontend
        foreach ($criteria->getFilters() as $filter) {
            static::assertNotInstanceOf(BlogAvailableFilter::class, $filter);
        }
    }

    public function testChannelContextIsCreatedPerChannel(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $this->createCheck([[], [], []])->run();

        static::assertSame([
            $this->ids->get('sales-channel-1'),
            $this->ids->get('sales-channel-2'),
            $this->ids->get('sales-channel-3'),
        ], array_map(static fn (ChannelDomain $domain) => $domain->channelId, $this->contextDomains));
    }

    /**
     * A domain whose context cannot be created, e.g. because its currency is no longer assigned to the sales
     * channel, is that sales channel's failure. The other sales channels are still probed and reported.
     */
    public function testADomainWithoutContextFailsOnItsOwn(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->domainsWithoutContext = [$this->ids->get('domain-sales-channel-1')];

        $result = $this->createCheck([[$this->ids->get('blog-2')], []])->run();

        static::assertSame(Status::ERROR, $result->status);
        static::assertFalse($result->healthy);
        static::assertCount(2, $result->extra);
        static::assertSame(Response::HTTP_BAD_REQUEST, $result->extra[0]['responseCode']);
        static::assertSame('no context for this domain', $result->extra[0]['errorMessage']);
        static::assertSame(Response::HTTP_OK, $result->extra[1]['responseCode']);
        static::assertSame(1, $this->handledRequests);
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function closeoutConfigProvider(): iterable
    {
        yield 'the setting hides closeout blogs out of stock' => [true, true];
        yield 'the setting keeps them renderable' => [false, false];
    }

    /**
     * `BlogDetailRoute::addCloseoutFilter()` only filters while the setting is on, so the check has
     * to do the same. Filtering unconditionally would skip a sales channel whose only blogs are
     * closeout and out of stock, even though their detail pages still render.
     */
    #[DataProvider('closeoutConfigProvider')]
    #[TestDox('When $_dataName, the closeout filter is applied: $1')]
    public function testTheCloseoutFilterFollowsTheChannelSetting(bool $hideCloseoutBlogs, bool $expectFilter): void
    {
        $this->hideCloseoutBlogs = $hideCloseoutBlogs;

        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $criteria = null;
        $this->createCheck([
            function (Criteria $actual) use (&$criteria) {
                $criteria = $actual;

                return [];
            },
            [],
            [],
        ])->run();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertEquals($expectFilter ? [new BlogCloseoutFilter()] : [], $criteria->getFilters());
    }

    /**
     * The URL that is probed belongs to one domain, and the context of that domain feeds the criteria
     * processing that decides which blogs are visible. Looking the blog up with any other context
     * would evaluate a restriction against a different context than the request that follows.
     */
    #[TestDox('The lookup context describes the domain whose URL is probed, not the sales channel defaults')]
    public function testTheLookupContextIsBuiltForTheProbedDomain(): void
    {
        $this->initDomainMocks();
        $this->initHandleRequest(Response::HTTP_OK);
        $this->initCreateEmptyResult();

        $this->createCheck([[], [], []])->run();

        static::assertSame([
            $this->ids->get('domain-sales-channel-1'),
            $this->ids->get('domain-sales-channel-2'),
            $this->ids->get('domain-sales-channel-3'),
        ], array_map(static fn (ChannelDomain $domain) => $domain->id, $this->contextDomains));
    }

    /**
     * @param array<callable(Criteria, ChannelContext): list<string>|list<string>> $searchResults
     */
    private function createCheck(array $searchResults = []): BlogDetailReadinessCheck
    {
        /** @var StaticChannelRepository<ChannelBlogCollection> $blogRepository */
        $blogRepository = new StaticChannelRepository($searchResults);

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturnCallback(
            fn (string $key): bool => $key === 'core.listing.hideCloseoutBlogsWhenOutOfStock' && $this->hideCloseoutBlogs
        );

        return new BlogDetailReadinessCheck(
            $this->util,
            $this->domainProvider,
            $blogRepository,
            $this->contextFactory,
            new BlogCloseoutFilterFactory(),
            $systemConfigService,
        );
    }

    /**
     * @return list<list<string>>
     */
    private function blogSearchResults(): array
    {
        // the third sales channel has no blog the frontend would render
        return [
            [$this->ids->get('blog-1')],
            [$this->ids->get('blog-2')],
            [],
        ];
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

        $this->util->method('generateDomainUrl')->willReturnCallback(static function ($domain, $routeName) {
            return $domain . $routeName;
        });
    }

    private function initHandleRequest(int $responseCode): void
    {
        $this->util->method('handleRequest')->willReturnCallback(
            function () use ($responseCode): FrontendHealthCheckResult {
                ++$this->handledRequests;

                return FrontendHealthCheckResult::create(
                    'http://localhost:8000/blog/123',
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
                'BlogDetailReadiness',
                Status::SKIPPED,
                'No sales channels with blog detail pages found.',
                true,
                []
            ));
    }
}
