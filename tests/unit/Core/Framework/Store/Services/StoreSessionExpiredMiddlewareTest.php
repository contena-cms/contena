<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Contena\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Contena\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(StoreSessionExpiredMiddleware::class)]
class StoreSessionExpiredMiddlewareTest extends TestCase
{
    public function testReturnsResponseIfStatusCodeIsNotUnauthorized(): void
    {
        $response = new Response(200, [], '{"payload":"data"}');
        $request = new Psr7Request('GET', '/');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $middleware = new StoreSessionExpiredMiddleware($connection, new RequestStack());

        $handledResponse = $this->invoke($middleware, $response, $request);

        static::assertSame($response, $handledResponse);
    }

    public function testReturnsResponseWithRewoundBodyIfCodeIsNotMatched(): void
    {
        $response = new Response(401, [], '{"payload":"data"}');
        $request = new Psr7Request('GET', '/');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $middleware = new StoreSessionExpiredMiddleware($connection, new RequestStack());

        $handledResponse = $this->invoke($middleware, $response, $request);

        static::assertSame($response, $handledResponse);
        static::assertSame('{"payload":"data"}', (string) $handledResponse->getBody());
    }

    #[DataProvider('provideRequestStacks')]
    public function testThrowsIfApiRespondsWithTokenExpiredException(RequestStack $requestStack): void
    {
        $response = new Response(401, [], '{"code":"ContenaPlatformException-1"}');
        $request = new Psr7Request('GET', '/');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $middleware = new StoreSessionExpiredMiddleware($connection, $requestStack);

        $this->expectException(StoreSessionExpiredException::class);
        $this->invoke($middleware, $response, $request);
    }

    public function testLogsOutUserByInvalidToken(): void
    {
        $admin_userId = Uuid::randomHex();
        $token = Uuid::randomHex();
        $requestStack = new RequestStack([
            new Request([], [], ['sw-context' => new Context(new AdminApiSource($admin_userId))]),
        ]);

        $response = new Response(401, [], '{"code":"ContenaPlatformException-1"}');
        $request = new Psr7Request('GET', '/', headers: [
            StoreRequestOptionsProvider::CONTENA_PLATFORM_TOKEN_HEADER => $token,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(static::isString(), ['token' => $token]);

        $middleware = new StoreSessionExpiredMiddleware($connection, $requestStack);

        $this->expectException(StoreSessionExpiredException::class);
        $this->invoke($middleware, $response, $request);
    }

    public function testLogsOutUserAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ContenaPlatformException-1"}');

        $adminUser = new AdminUserEntity();
        $adminUser->setId('592d9499bea4417e929622ed0e92ba8b');

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $sfRequest = new Request([], [], ['sw-context' => $context]);

        $requestStack = new RequestStack();
        $requestStack->push($sfRequest);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(static::anything(), ['admin_userId' => Uuid::fromHexToBytes($adminUser->getId())]);

        $middleware = new StoreSessionExpiredMiddleware($connection, $requestStack);

        $request = new Psr7Request('GET', '/');

        $this->expectException(StoreSessionExpiredException::class);
        $this->invoke($middleware, $response, $request);
    }

    public static function provideRequestStacks(): \Generator
    {
        yield 'request stack without request' => [new RequestStack()];

        $requestStackWithoutContext = new RequestStack();
        $requestStackWithoutContext->push(new Request());

        yield 'request stack without context' => [$requestStackWithoutContext];

        $requestStackWithWrongSource = new RequestStack();
        $requestStackWithWrongSource->push(new Request([], [], ['sw-context' => Context::createDefaultContext()]));

        yield 'request stack with wrong source' => [$requestStackWithWrongSource];

        $requestStackWithMissingUserId = new RequestStack();
        $requestStackWithMissingUserId->push(new Request([], [], ['sw-context' => new Context(new AdminApiSource(null))]));

        yield 'request stack with missing admin_user id' => [$requestStackWithMissingUserId];
    }

    private function invoke(StoreSessionExpiredMiddleware $middleware, Response $response, Psr7Request $request): mixed
    {
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);

        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);

        return $promise->wait();
    }
}
