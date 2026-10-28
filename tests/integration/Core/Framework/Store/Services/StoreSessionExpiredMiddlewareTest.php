<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Contena\Core\Framework\Store\Exception\StoreSessionExpiredException;
use Contena\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class StoreSessionExpiredMiddlewareTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testLogsOutUserAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ContenaPlatformException-1"}');

        $adminUser = $this->userRepository->search(new Criteria(), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($adminUser);
        $this->userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 's3cr3t',
        ]], Context::createDefaultContext());

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $request = new Request(
            [],
            [],
            [
                'sw-context' => $context,
            ]
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $middleware = new StoreSessionExpiredMiddleware(
            static::getContainer()->get(Connection::class),
            $requestStack
        );

        $request = new Psr7Request('GET', '/');

        $this->expectExceptionObject(new StoreSessionExpiredException());
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);
        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);

        try {
            $promise->wait();
        } finally {
            $adminUser = $this->userRepository->search(new Criteria([$adminUser->getId()]), Context::createDefaultContext())->getEntities()->first();
            static::assertNotNull($adminUser);
            static::assertNull($adminUser->getStoreToken());
        }
    }

    public function testLogsOutUserByTokenAndThrowsIfApiRespondsWithTokenExpiredException(): void
    {
        $response = new Response(401, [], '{"code":"ContenaPlatformException-1"}');

        $expiredSessionUserId = Uuid::randomHex();
        $loginSessionUserId = Uuid::randomHex();

        $this->createAdminUser($expiredSessionUserId, 'some-invalid-token');
        $this->createAdminUser($loginSessionUserId, 'some-valid-token');

        $request = new Request(attributes: ['sw-context' => new Context(new AdminApiSource($loginSessionUserId))]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $middleware = new StoreSessionExpiredMiddleware(
            static::getContainer()->get(Connection::class),
            $requestStack
        );

        $request = new Psr7Request('GET', '/', [StoreRequestOptionsProvider::CONTENA_PLATFORM_TOKEN_HEADER => 'some-invalid-token']);

        $this->expectExceptionObject(new StoreSessionExpiredException());
        $handler = fn (RequestInterface $req, array $options) => new FulfilledPromise($response);
        /** @var PromiseInterface $promise */
        $promise = ($middleware($handler))($request, []);

        try {
            $promise->wait();
        } finally {
            $adminUsers = $this->userRepository->search(new Criteria([$expiredSessionUserId, $loginSessionUserId]), Context::createDefaultContext())->getEntities();
            $expiredSessionUser = $adminUsers->get($expiredSessionUserId);
            $loginSessionUser = $adminUsers->get($loginSessionUserId);
            static::assertNotNull($expiredSessionUser);
            static::assertNotNull($loginSessionUser);
            static::assertNull($expiredSessionUser->getStoreToken());
            static::assertSame('some-valid-token', $loginSessionUser->getStoreToken());
        }
    }

    private function createAdminUser(string $userId, string $storeToken): void
    {
        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => Uuid::randomHex() . 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => Uuid::randomHex() . '@bar.com',
                'storeToken' => $storeToken,
            ],
        ];

        $this->userRepository->create($data, Context::createDefaultContext());
    }
}
