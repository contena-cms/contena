<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UserController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<EntityCollection<Entity>> $userRoleRepository
     * @param EntityRepository<AclRoleCollection> $roleRepository
     * @param EntityRepository<UserAccessKeyCollection> $keyRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $userRoleRepository,
        private readonly EntityRepository $roleRepository,
        private readonly EntityRepository $keyRepository,
        private readonly UserDefinition $userDefinition,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AbstractNumberRangeValueGenerator $numberRangeValueGenerator,
    ) {
    }

    #[Route(
        path: '/api/_info/me',
        name: 'api.info.me',
        methods: [Request::METHOD_GET]
    )]
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn();
        }
        $criteria = new Criteria([$userId]);
        $criteria->addAssociations(['aclRoles', 'avatarMedia', 'tenant']);

        $user = $this->userRepository->search($criteria, $context)->getEntities()->first();
        if (!$user) {
            throw OAuthServerException::invalidCredentials();
        }

        return $responseFactory->createDetailResponse(new Criteria(), $user, $this->userDefinition, $request, $context);
    }

    #[Route(
        path: '/api/_info/me',
        name: 'api.change.me',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['user_change_me'],
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateMe(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn();
        }

        $allowedChanges = ['name', 'phoneNumber', 'username', 'localeId', 'email', 'avatarMedia', 'avatarId', 'password', 'timeZone'];

        if (array_diff(array_keys($request->request->all()), $allowedChanges) !== []) {
            throw ApiException::missingPrivileges(['user:update']);
        }

        return $this->upsertUser($userId, $request, $context, $responseFactory);
    }

    #[Route(
        path: '/api/_info/ping',
        name: 'api.info.ping',
        methods: [Request::METHOD_GET]
    )]
    public function status(Context $context): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn();
        }
        $result = $this->userRepository->searchIds(new Criteria([$userId]), $context);

        if ($result->getTotal() === 0) {
            throw OAuthServerException::invalidCredentials();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/user/logout',
        name: 'api.action.user.logout',
        methods: [Request::METHOD_POST]
    )]
    public function logout(Context $context): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn();
        }

        $this->refreshTokenRepository->revokeRefreshTokensForUser($userId);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/user/{userId}',
        name: 'api.user.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['user:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteUser(string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $source = $context->getSource();

        if ((!$source instanceof AdminApiSource)
            || (!$source->isAllowed('user:update')
            && $source->getUserId() !== $userId)
        ) {
            throw new PermissionDeniedException();
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId): void {
            $this->userRepository->delete([['id' => $userId]], $context);
        });

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $userId, $request, $context);
    }

    #[Route(
        path: '/api/user/{userId}/access-keys/{id}',
        name: 'api.user_access_keys.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['user_access_key:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteUserAccessKey(string $id, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->keyRepository->delete([['id' => $id]], $context);
        });

        return $factory->createRedirectResponse($this->keyRepository->getDefinition(), $id, $request, $context);
    }

    #[Route(
        path: '/api/user',
        name: 'api.user.create',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['user:create'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function upsertUser(?string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $data = $request->request->all();
        if (!isset($data['id'])) {
            $data['id'] = null;
        }
        $data['id'] = $userId ?: $data['id'];

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw new PermissionDeniedException();
        }

        $isSelfUpdate = $source->getUserId() === $data['id'];
        $canUpdateUsers = $source->isAllowed('user:update');

        if (!$canUpdateUsers && !$isSelfUpdate) {
            throw new PermissionDeniedException();
        }

        $isTryingToChangeAdmin = isset($data['admin']);

        if ($data['id'] === null) {
            $userCode = $data['userCode'] ?? null;
            if ($userCode === null || $userCode === '') {
                $data['userCode'] = $this->numberRangeValueGenerator->getValue('user', $context);
            }
        }

        if (!$source->isAdmin() && $isTryingToChangeAdmin) {
            throw new PermissionDeniedException();
        }

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->userRepository->upsert([$data], $context));
        $eventIds = $events->getEventByEntityName(UserDefinition::ENTITY_NAME)?->getIds() ?? [];
        $entityId = array_last($eventIds);
        \assert(\is_string($entityId), 'There should be a user ID, as it just was written');

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(
        path: '/api/user/{userId}',
        name: 'api.user.update',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['user:update'],
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateUser(?string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertUser($userId, $request, $context, $factory);
    }

    #[Route(
        path: '/api/acl-role',
        name: 'api.acl_role.create',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['acl_role:create'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function upsertRole(?string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $data = $request->request->all();

        if (!isset($data['id'])) {
            $data['id'] = $roleId ?? null;
        }

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->roleRepository->upsert([$data], $context));
        $eventIds = $events->getEventByEntityName(AclRoleDefinition::ENTITY_NAME)?->getIds() ?? [];
        $entityId = array_last($eventIds);
        \assert($entityId !== null);

        return $factory->createRedirectResponse($this->roleRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(
        path: '/api/acl-role/{roleId}',
        name: 'api.acl_role.update',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['acl_role:update'],
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateRole(?string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertRole($roleId, $request, $context, $factory);
    }

    #[Route(
        path: '/api/user/{userId}/acl-roles/{roleId}',
        name: 'api.user_role.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['acl_user_role:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteUserRole(string $userId, string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId, $userId): void {
            $this->userRoleRepository->delete([['userId' => $userId, 'aclRoleId' => $roleId]], $context);
        });

        return $factory->createRedirectResponse($this->userRoleRepository->getDefinition(), $roleId, $request, $context);
    }

    #[Route(
        path: '/api/acl-role/{roleId}',
        name: 'api.acl_role.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['acl_role:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteRole(string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId): void {
            $this->roleRepository->delete([['id' => $roleId]], $context);
        });

        return $factory->createRedirectResponse($this->roleRepository->getDefinition(), $roleId, $request, $context);
    }
}
