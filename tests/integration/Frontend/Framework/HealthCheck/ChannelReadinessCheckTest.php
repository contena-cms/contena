<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Contena\Frontend\Framework\SystemCheck\Util\FrontendHealthCheckResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ChannelsReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use ChannelApiTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testWhereAllChannelsAreReturningHealthy(): void
    {
        $this->createChannels();
        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testWhereOneChannelIsReturningHealthyWithMocks(): void
    {
        $this->createChannels();

        $util = $this->createUtilMock();
        $util->expects($this->exactly(2))
            ->method('handleRequest')
            ->willReturnOnConsecutiveCalls(
                FrontendHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_OK,
                    1.23,
                ),
                FrontendHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_BAD_REQUEST,
                    1.23,
                ),
            );

        $check = $this->createCheck($util);
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame(Status::ERROR, $result->status);
    }

    public function testWhenAllAreReturningErrorWithMocks(): void
    {
        $this->createChannels();

        $util = $this->createUtilMock();
        $util->expects($this->exactly(2))
            ->method('handleRequest')
            ->willReturnOnConsecutiveCalls(
                FrontendHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_BAD_REQUEST,
                    1.23,
                ),
                FrontendHealthCheckResult::create(
                    'http://localhost:8000/',
                    Response::HTTP_BAD_REQUEST,
                    1.23,
                ),
            );

        $check = $this->createCheck($util);
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame(Status::FAILURE, $result->status);
    }

    public function testTrustedHostsAreTheSameBeforeAndAfterCheck(): void
    {
        // empty test state, if this assertion fails, some other test is leaking.
        static::assertCount(0, Request::getTrustedHosts());
        Request::setTrustedHosts(['foo.bar', 'test.com']);
        $trustedHostsBefore = Request::getTrustedHosts();
        $check = $this->createCheck();
        $check->run();

        static::assertSame($trustedHostsBefore, Request::getTrustedHosts());
        // reset the trusted hosts to avoid leaking state
        Request::setTrustedHosts([]);
    }


    private function createChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `channel_domain`');
        $ids = new IdsCollection();
        $this->createChannel([
            'id' => $ids->create('sales-channel-1'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $this->createChannel([
            'id' => $ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://shop.test',
                ],
            ],
        ]);
    }

    private function createUtilMock(): ChannelDomainUtil&MockObject
    {
        $util = $this->createMock(ChannelDomainUtil::class);
        $this->initUtilMock($util);

        return $util;
    }

    private function initUtilMock(ChannelDomainUtil&MockObject $util): void
    {
        $util->method('runAsChannelRequest')
            ->willReturnCallback(static function (callable $callback): mixed {
                return $callback();
            });

        $util->method('runWhileTrustingAllHosts')
            ->willReturnCallback(static function (callable $callback): mixed {
                return $callback();
            });

        $util->method('generateDomainUrl')->willReturnCallback(static function ($domain, $routeName) {
            return $domain . $routeName;
        });
    }
}
