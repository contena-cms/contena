<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ShopApiSource;
use Contena\Core\Framework\Api\Controller\AdminUserController;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Sso\SsoService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminUserController::class)]
class AdminUserControllerTest extends TestCase
{
    public function testLogoutRevokesTokensAndReturnsNoContent(): void
    {
        $adminUserId = 'test-admin_user-id';

        $ssoService = $this->createMock(SsoService::class);
        $ssoService->expects($this->once())
            ->method('revokeUserTokens')
            ->with($adminUserId);

        $controller = $this->createController($ssoService);
        $context = Context::createDefaultContext(new AdminApiSource($adminUserId));

        $response = $controller->logout($context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLogoutThrowsForNonAdminApiSource(): void
    {
        static::expectExceptionObject(ApiException::invalidAdminSource(ShopApiSource::class));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new ShopApiSource('test-channel'));

        $controller->logout($context);
    }

    public function testLogoutThrowsWhenUserIdIsNull(): void
    {
        static::expectExceptionObject(ApiException::admin_userNotLoggedIn());

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource(null));

        $controller->logout($context);
    }

    public function testUpdateMeAllowsChangingTimezone(): void
    {
        $adminUserId = 'test-admin_user-id';
        $context = Context::createDefaultContext(new AdminApiSource($adminUserId));
        $request = Request::create('/', Request::METHOD_PATCH, ['timeZone' => 'Europe/Berlin']);
        $adminUserDefinition = new AdminUserDefinition();
        $adminUserRepository = StaticEntityRepository::of(AdminUserCollection::class, [], $adminUserDefinition);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $response = new Response();
        $responseFactory->expects($this->once())
            ->method('createRedirectResponse')
            ->with($adminUserDefinition, $adminUserId, $request, $context)
            ->willReturn($response);

        $controller = $this->createController(admin_userRepository: $adminUserRepository, admin_userDefinition: $adminUserDefinition);

        static::assertSame($response, $controller->updateMe($context, $request, $responseFactory));
        static::assertSame('Europe/Berlin', $adminUserRepository->upserts[0][0]['timeZone']);
    }

    /**
     * updateMe() must reject any field that is not part of the self-service profile allow-list.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('forbiddenUpdateMePayloadProvider')]
    public function testUpdateMeRejectsFieldsOutsideTheSelfProfileAllowlist(array $payload): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['admin_user:update']));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource('test-admin_user-id'));
        $request = Request::create('/', Request::METHOD_PATCH, $payload);

        $controller->updateMe($context, $request, static::createStub(ResponseFactoryInterface::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function forbiddenUpdateMePayloadProvider(): iterable
    {
        yield 'field outside the profile allow-list' => [[
            'title' => 'Dr.',
        ]];

        yield 'another field outside the profile allow-list' => [[
            'active' => false,
        ]];

        yield 'custom fields outside the profile allow-list' => [[
            'customFields' => ['foo' => 'bar'],
        ]];
    }

    public function testUpdateMeAllowsLinkingAnAvatarMedia(): void
    {
        $adminUserId = 'test-admin_user-id';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext(new AdminApiSource($adminUserId));
        $request = Request::create('/', Request::METHOD_PATCH, ['avatarMedia' => ['id' => $mediaId]]);
        $adminUserDefinition = new AdminUserDefinition();
        $adminUserRepository = StaticEntityRepository::of(AdminUserCollection::class, [], $adminUserDefinition);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects($this->once())
            ->method('createRedirectResponse')
            ->willReturn(new Response());

        $controller = $this->createController(admin_userRepository: $adminUserRepository, admin_userDefinition: $adminUserDefinition);
        $controller->updateMe($context, $request, $responseFactory);

        static::assertSame(['id' => $mediaId], $adminUserRepository->upserts[0][0]['avatarMedia']);
    }

    /**
     * updateMe() accepts `avatarMedia` only as an id link; every other shape must be rejected.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('forbiddenAvatarMediaPayloadProvider')]
    public function testUpdateMeRejectsAvatarMediaBeyondAnIdLink(array $payload): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['admin_user:update']));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource('test-admin_user-id'));
        $request = Request::create('/', Request::METHOD_PATCH, $payload);

        $controller->updateMe($context, $request, static::createStub(ResponseFactoryInterface::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function forbiddenAvatarMediaPayloadProvider(): iterable
    {
        yield 'nested admin_user association' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'admin_user' => ['id' => Uuid::randomHex()]],
        ]];

        yield 'nested avatarUsers association' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'avatarUsers' => [['id' => Uuid::randomHex()]]],
        ]];

        yield 'nested association in the extensions container' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'extensions' => ['admin_user' => ['id' => Uuid::randomHex()]]],
        ]];

        yield 'extra scalar field next to the id' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'fileName' => 'renamed'],
        ]];

        yield 'link without an id' => [[
            'avatarMedia' => ['fileName' => 'renamed'],
        ]];

        yield 'empty link' => [[
            'avatarMedia' => [],
        ]];

        yield 'non string id' => [[
            'avatarMedia' => ['id' => ['nested']],
        ]];

        yield 'plain id instead of a link' => [[
            'avatarMedia' => Uuid::randomHex(),
        ]];

        yield 'null' => [[
            'avatarMedia' => null,
        ]];
    }

    /**
     * @param EntityRepository<AdminUserCollection>|null $adminUserRepository
     */
    private function createController(
        ?SsoService $ssoService = null,
        ?EntityRepository $adminUserRepository = null,
        ?AdminUserDefinition $adminUserDefinition = null,
    ): AdminUserController {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $func) => $func($connection));

        return new AdminUserController(
            $adminUserRepository ?? static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $adminUserDefinition ?? static::createStub(AdminUserDefinition::class),
            $ssoService ?? static::createStub(SsoService::class),
            $connection,
        );
    }
}
