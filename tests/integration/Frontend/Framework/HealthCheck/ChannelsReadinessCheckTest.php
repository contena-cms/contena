<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\SystemCheck\ChannelsReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Contena\Frontend\Framework\SystemCheck\Util\FrontendHealthCheckResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ChannelsReadinessCheckTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testWhereAllChannelsAreReturningHealthy(): void
    {
        $this->createChannels();
        $result = $this->createCheck()->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::OK, $result->status);
    }

    public function testWhereOneChannelIsReturningHealthyWithMocks(): void
    {
        $this->createChannels();
        $util = $this->createUtilMock();
        $util->expects($this->exactly(2))->method('handleRequest')->willReturnOnConsecutiveCalls(
            FrontendHealthCheckResult::create('http://localhost:8000/', Response::HTTP_OK, 1.23),
            FrontendHealthCheckResult::create('http://localhost:8000/', Response::HTTP_BAD_REQUEST, 1.23),
        );

        $result = $this->createCheck($util)->run();

        static::assertFalse($result->healthy);
        static::assertSame(Status::ERROR, $result->status);
    }

    public function testWhenAllAreReturningErrorWithMocks(): void
    {
        $this->createChannels();
        $util = $this->createUtilMock();
        $util->expects($this->exactly(2))->method('handleRequest')->willReturnOnConsecutiveCalls(
            FrontendHealthCheckResult::create('http://localhost:8000/', Response::HTTP_BAD_REQUEST, 1.23),
            FrontendHealthCheckResult::create('http://localhost:8000/', Response::HTTP_BAD_REQUEST, 1.23),
        );

        $result = $this->createCheck($util)->run();

        static::assertFalse($result->healthy);
        static::assertSame(Status::FAILURE, $result->status);
    }

    public function testTrustedHostsAreTheSameBeforeAndAfterCheck(): void
    {
        static::assertEmpty(Request::getTrustedHosts());
        Request::setTrustedHosts(['foo.bar', 'test.com']);
        $trustedHostsBefore = Request::getTrustedHosts();

        $this->createCheck()->run();

        static::assertSame($trustedHostsBefore, Request::getTrustedHosts());
        Request::setTrustedHosts([]);
    }

    private function createCheck((ChannelDomainUtil&MockObject)|null $util = null): ChannelsReadinessCheck
    {
        return new ChannelsReadinessCheck(
            $util ?? static::getContainer()->get(ChannelDomainUtil::class),
            static::getContainer()->get(ChannelDomainProvider::class),
        );
    }

    private function createChannels(): void
    {
        $this->connection->executeStatement('DELETE FROM `channel_domain`');
        $contentLayoutId = $this->findContentLayoutId('category');
        $ids = new IdsCollection();
        foreach (['channel-1' => 'http://example.com', 'channel-2' => 'http://shop.test'] as $key => $url) {
            $channel = $this->createChannel([
                'id' => $ids->create($key),
                'domains' => [[
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $url,
                ]],
            ]);
            static::getContainer()->get('category_content_layout.repository')->create([[
                'id' => Uuid::randomHex(),
                'categoryId' => $channel['navigationCategoryId'],
                'channelId' => $channel['id'],
                'contentLayoutId' => $contentLayoutId,
            ]], Context::createDefaultContext());
        }
    }

    private function findContentLayoutId(string $rootSource): string
    {
        $criteria = new Criteria()->addFilter(new EqualsFilter('rootSource', $rootSource))->setLimit(1);
        $id = static::getContainer()->get('content_layout.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        \assert($id !== null);

        return $id;
    }

    private function createUtilMock(): ChannelDomainUtil&MockObject
    {
        $util = $this->createMock(ChannelDomainUtil::class);
        $util->method('runAsChannelRequest')->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $util->method('runWhileTrustingAllHosts')->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $util->method('generateDomainUrl')->willReturnCallback(static fn (string $domain, string $routeName): string => $domain . $routeName);

        return $util;
    }
}
