<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\Framework\Routing\ChannelApiDomainResolver;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
class ChannelApiDomainResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private ChannelApiDomainResolver $resolver;

    private string $domainUrl;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->resolver = static::getContainer()->get(ChannelApiDomainResolver::class);

        $domainUrl = $this->connection->fetchOne(
            'SELECT url FROM channel_domain WHERE channel_id = :channelId LIMIT 1',
            ['channelId' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
        );
        static::assertIsString($domainUrl);
        $this->domainUrl = $domainUrl;
    }

    public function testResolvesChannelDomainAndLanguageFromHeader(): void
    {
        $request = $this->createRequest($this->domainUrl);

        $this->resolver->resolveDomain($this->createEvent($request));

        static::assertIsString($request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_ID));
        static::assertIsString($request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
        static::assertNotSame('', $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testExplicitLanguageHeaderTakesPrecedence(): void
    {
        $request = $this->createRequest($this->domainUrl);
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM);

        $this->resolver->resolveDomain($this->createEvent($request));

        static::assertSame(Defaults::LANGUAGE_SYSTEM, $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testUnknownDomainFailsWithRoutingException(): void
    {
        $request = $this->createRequest('https://unknown.invalid');

        $this->expectExceptionObject(RoutingException::channelDomainNotFound('https://unknown.invalid'));
        $this->resolver->resolveDomain($this->createEvent($request));
    }

    private function createRequest(string $domainUrl): Request
    {
        $request = Request::create('/channel-api/test');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ChannelApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, $domainUrl);

        return $request;
    }

    private function createEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::getContainer()->get('kernel'),
            static fn (): Response => new Response(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
