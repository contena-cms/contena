<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Api\Controller\UserController;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UserController::class)]
class UserControllerTest extends TestCase
{
    public function testLogoutRevokesTokensAndReturnsNoContent(): void
    {
        $userId = 'test-user-id';

        $refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepository->expects($this->once())
            ->method('revokeRefreshTokensForUser')
            ->with($userId);

        $controller = $this->createController($refreshTokenRepository);
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $response = $controller->logout($context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLogoutThrowsForNonAdminApiSource(): void
    {
        static::expectExceptionObject(ApiException::invalidAdminSource(SystemSource::class));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new SystemSource());

        $controller->logout($context);
    }

    public function testLogoutThrowsWhenUserIdIsNull(): void
    {
        static::expectExceptionObject(ApiException::userNotLoggedIn());

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource(null));

        $controller->logout($context);
    }

    public function testUpdateMeAllowsChangingTimezone(): void
    {
        $userId = 'test-user-id';
        $context = Context::createDefaultContext(new AdminApiSource($userId));
        $request = Request::create('/', Request::METHOD_PATCH, ['timeZone' => 'Europe/Berlin']);
        $userDefinition = new UserDefinition();
        $userRepository = StaticEntityRepository::of(UserCollection::class, [], $userDefinition);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $response = new Response();
        $responseFactory->expects($this->once())
            ->method('createRedirectResponse')
            ->with($userDefinition, $userId, $request, $context)
            ->willReturn($response);

        $controller = $this->createController(userRepository: $userRepository, userDefinition: $userDefinition);

        static::assertSame($response, $controller->updateMe($context, $request, $responseFactory));
        static::assertSame('Europe/Berlin', $userRepository->upserts[0][0]['timeZone']);
    }

    public function testUpdateMeRejectsFieldsOutsideTheSelfProfileAllowlist(): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['user:update']));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource('test-user-id'));
        $request = Request::create('/', Request::METHOD_PATCH, ['title' => 'Dr.']);

        $controller->updateMe($context, $request, static::createStub(ResponseFactoryInterface::class));
    }

    public function testTenantUpdateStoresMembershipPropertiesSeparately(): void
    {
        $userId = '0123456789abcdef0123456789abcdef';
        $tenantId = 'abcdef0123456789abcdef0123456789';
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);
        $context = Context::createTenantContext($tenantId, $source);
        $userRepository = StaticEntityRepository::of(UserCollection::class, [], new UserDefinition());
        /** @var StaticEntityRepository<EntityCollection<Entity>> $membershipRepository */
        $membershipRepository = StaticEntityRepository::of(EntityCollection::class);
        $controller = $this->createController(userRepository: $userRepository, userTenantRepository: $membershipRepository);
        $request = Request::create('/', Request::METHOD_PATCH, ['active' => false, 'admin' => true, 'userCode' => 'TENANT-1']);
        $responseFactory = static::createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createRedirectResponse')->willReturn(new Response());

        $controller->updateUser($userId, $request, $context, $responseFactory);

        static::assertSame([], $userRepository->upserts);
        static::assertSame([[
            'userId' => $userId,
            'tenantId' => $tenantId,
            'active' => false,
            'admin' => true,
            'userCode' => 'TENANT-1',
        ]], $membershipRepository->upserts[0]);
    }

    public function testTenantDeleteOnlyRemovesMembership(): void
    {
        $userId = '0123456789abcdef0123456789abcdef';
        $tenantId = 'abcdef0123456789abcdef0123456789';
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);
        $context = Context::createTenantContext($tenantId, $source);
        $userRepository = StaticEntityRepository::of(UserCollection::class, [], new UserDefinition());
        /** @var StaticEntityRepository<EntityCollection<Entity>> $membershipRepository */
        $membershipRepository = StaticEntityRepository::of(EntityCollection::class);
        $controller = $this->createController(userRepository: $userRepository, userTenantRepository: $membershipRepository);
        $responseFactory = static::createStub(ResponseFactoryInterface::class);
        $responseFactory->method('createRedirectResponse')->willReturn(new Response());

        $controller->deleteUser($userId, new Request(), $context, $responseFactory);

        static::assertSame([], $userRepository->deletes);
        static::assertSame([[[
            'userId' => $userId,
            'tenantId' => $tenantId,
        ]]], $membershipRepository->deletes);
    }

    /**
     * @param EntityRepository<UserCollection>|null $userRepository
     * @param EntityRepository<EntityCollection<Entity>>|null $userTenantRepository
     */
    private function createController(
        ?RefreshTokenRepository $refreshTokenRepository = null,
        ?EntityRepository $userRepository = null,
        ?EntityRepository $userTenantRepository = null,
        ?UserDefinition $userDefinition = null,
    ): UserController {
        /** @var EntityRepository<EntityCollection<Entity>> $membershipRepository */
        $membershipRepository = $userTenantRepository ?? static::createStub(EntityRepository::class);

        return new UserController(
            $userRepository ?? static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $membershipRepository,
            $userDefinition ?? static::createStub(UserDefinition::class),
            $refreshTokenRepository ?? static::createStub(RefreshTokenRepository::class),
            static::createStub(AbstractNumberRangeValueGenerator::class),
        );
    }
}
