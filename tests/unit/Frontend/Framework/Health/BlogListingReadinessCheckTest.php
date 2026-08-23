<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\BlogListingReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainCollection;
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

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->domainProvider = static::createStub(AbstractChannelDomainProvider::class);
        $this->ids = new IdsCollection();

        $this->initUtilMock();
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

        $this->util->method('handleRequest')->willReturn(
            FrontendHealthCheckResult::create(
                'http://localhost:8000/blog',
                Response::HTTP_OK,
                1.23
            )
        );

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('BlogListingReadiness', $result->name);
        static::assertSame('Blog listing pages are OK for provided channels.', $result->message);
        static::assertSame('OK', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(200, $result->extra[0]['responseCode']);
        static::assertSame(200, $result->extra[1]['responseCode']);
    }

    public function testRunSkipped(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->initCreateEmptyResult();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('BlogListingReadiness', $result->name);
        static::assertSame('No channels with blog listing pages found.', $result->message);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertCount(0, $result->extra);
    }

    public function testRunFailed(): void
    {
        $this->initDataMocks();

        $this->util->method('handleRequest')->willReturn(
            FrontendHealthCheckResult::create(
                'http://localhost:8000/blog',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                1.23
            )
        );

        $check = $this->createCheck();
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame('BlogListingReadiness', $result->name);
        static::assertSame('Some or all blog listing pages are unhealthy.', $result->message);
        static::assertSame('FAILURE', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(500, $result->extra[0]['responseCode']);
        static::assertSame(500, $result->extra[1]['responseCode']);
    }

    private function createCheck(): BlogListingReadinessCheck
    {
        return new BlogListingReadinessCheck($this->util, $this->connection, $this->domainProvider);
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

        $this->util->method('generateDomainUrl')->willReturnCallback(static function ($domain, $routeName) {
            return $domain . $routeName;
        });
    }

    private function initDataMocks(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn(
            [
                ['id' => $this->ids->get('sales-channel-1'), 'category_id' => Uuid::randomHex()],
                ['id' => $this->ids->get('sales-channel-2'), 'category_id' => Uuid::randomHex()],
            ]
        );

        $collection = new ChannelDomainCollection([
            ChannelDomain::create($this->ids->get('sales-channel-1'), 'http://localhost:8000/de'),
            ChannelDomain::create($this->ids->get('sales-channel-2'), 'http://localhost:8000/en'),
            ChannelDomain::create($this->ids->get('sales-channel-3'), 'http://localhost:8000/invalid'),
        ]);

        $this->domainProvider->method('fetchChannelDomains')->willReturn($collection);
    }

    private function initCreateEmptyResult(): void
    {
        $this->util->method('createEmptyResult')
            ->willReturn(new Result(
                'BlogListingReadiness',
                Status::SKIPPED,
                'No channels with blog listing pages found.',
                true,
                []
            ));
    }
}
