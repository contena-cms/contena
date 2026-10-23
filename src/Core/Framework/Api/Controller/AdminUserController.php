<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\OAuth\Scope\AdminUserVerifiedScope;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Sso\SsoService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey\AdminUserAccessKeyCollection;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminUserController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserCollection> $admin_userRepository
     * @param EntityRepository<EntityCollection<Entity>> $admin_userRoleRepository
     * @param EntityRepository<AclRoleCollection> $roleRepository
     * @param EntityRepository<AdminUserAccessKeyCollection> $keyRepository
     */
    public function __construct(
        private readonly EntityRepository $admin_userRepository,
        private readonly EntityRepository $admin_userRoleRepository,
        private readonly EntityRepository $roleRepository,
        private readonly EntityRepository $keyRepository,
        private readonly AdminUserDefinition $admin_userDefinition,
        private readonly SsoService $ssoService,
        private readonly Connection $connection,
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

        $admin_userId = $context->getSource()->getUserId();
        if (!$admin_userId) {
            throw ApiException::admin_userNotLoggedIn();
        }
        $criteria = new Criteria([$admin_userId]);
        $criteria->addAssociations(['aclRoles', 'avatarMedia']);

        $admin_user = $this->admin_userRepository->search($criteria, $context)->getEntities()->first();
        if (!$admin_user) {
            throw OAuthServerException::invalidCredentials();
        }

        return $responseFactory->createDetailResponse(new Criteria(), $admin_user, $this->admin_userDefinition, $request, $context);
    }

    #[Route(
        path: '/api/_info/me',
        name: 'api.change.me',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['admin_user_change_me'],
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateMe(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        $admin_userId = $context->getSource()->getUserId();
        if (!$admin_userId) {
            throw ApiException::admin_userNotLoggedIn();
        }

        $allowedChanges = ['firstName', 'lastName', 'username', 'localeId', 'email', 'avatarMedia', 'avatarId', 'password', 'timeZone'];

        $changes = $request->request->all();
        if (array_diff(array_keys($changes), $allowedChanges) !== []) {
            throw ApiException::missingPrivileges(['admin_user:update']);
        }

        if (\array_key_exists('avatarMedia', $changes)) {
            $this->assertAvatarMediaIsLinkOnly($changes['avatarMedia']);
        }

        return $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context): Response => $this->upsertUser($admin_userId, $request, $context, $responseFactory),
        );
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

        $admin_userId = $context->getSource()->getUserId();
        if (!$admin_userId) {
            throw ApiException::admin_userNotLoggedIn();
        }
        $result = $this->admin_userRepository->searchIds(new Criteria([$admin_userId]), $context);

        if ($result->getTotal() === 0) {
            throw OAuthServerException::invalidCredentials();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/admin_user/logout',
        name: 'api.action.admin_user.logout',
        methods: [Request::METHOD_POST]
    )]
    public function logout(Context $context): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        $admin_userId = $context->getSource()->getUserId();
        if (!$admin_userId) {
            throw ApiException::admin_userNotLoggedIn();
        }

        $this->ssoService->revokeUserTokens($admin_userId);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/admin_user/{admin_userId}',
        name: 'api.admin_user.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['admin_user:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteUser(string $admin_userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $this->validateScope($request);

        $source = $context->getSource();

        if ((!$source instanceof AdminApiSource)
            || (!$source->isAllowed('admin_user:update')
            && $source->getUserId() !== $admin_userId)
        ) {
            throw new PermissionDeniedException();
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($admin_userId): void {
            $this->admin_userRepository->delete([['id' => $admin_userId]], $context);
        });

        return $factory->createRedirectResponse($this->admin_userRepository->getDefinition(), $admin_userId, $request, $context);
    }

    #[Route(
        path: '/api/admin_user/{admin_userId}/access-keys/{id}',
        name: 'api.admin_user_access_keys.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['admin_user_access_key:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteUserAccessKey(string $id, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $this->validateScope($request);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->keyRepository->delete([['id' => $id]], $context);
        });

        return $factory->createRedirectResponse($this->keyRepository->getDefinition(), $id, $request, $context);
    }

    #[Route(
        path: '/api/admin_user',
        name: 'api.admin_user.create',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['admin_user:create'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function upsertUser(?string $admin_userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $this->validateScope($request);

        $data = $request->request->all();
        $admin = $data['admin'] ?? null;
        $changesAdmin = isset($data['admin']);
        unset($data['admin']);

        $entityId = $admin_userId ?? $data['id'] ?? Uuid::randomHex();
        \assert(\is_string($entityId));
        $data['id'] = $entityId;

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw new PermissionDeniedException();
        }

        $isSelfUpdate = $source->getUserId() === $data['id'];
        $canUpdateUsers = $source->isAllowed('admin_user:update');

        if (!$canUpdateUsers && !$isSelfUpdate) {
            throw new PermissionDeniedException();
        }

        if (!$source->isAdmin() && $changesAdmin) {
            throw new PermissionDeniedException();
        }

        $this->connection->transactional(function () use ($data, $context, $changesAdmin, $admin, $entityId): void {
            $this->admin_userRepository->upsert([$data], $context);

            if ($changesAdmin) {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($entityId, $admin): void {
                    $this->admin_userRepository->update([['id' => $entityId, 'admin' => $admin]], $context);
                });
            }
        });

        return $factory->createRedirectResponse($this->admin_userRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(
        path: '/api/admin_user/{admin_userId}',
        name: 'api.admin_user.update',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['admin_user:update'],
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateUser(?string $admin_userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertUser($admin_userId, $request, $context, $factory);
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
        $this->validateScope($request);

        $data = $request->request->all();

        if (!isset($data['id'])) {
            $data['id'] = $roleId;
        }

        $events = $this->roleRepository->upsert([$data], $context);
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
        path: '/api/admin_user/{admin_userId}/acl-roles/{roleId}',
        name: 'api.admin_user_role.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['acl_user_role:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function deleteUserRole(string $admin_userId, string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        $this->validateScope($request);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId, $admin_userId): void {
            $this->admin_userRoleRepository->delete([['admin_userId' => $admin_userId, 'aclRoleId' => $roleId]], $context);
        });

        return $factory->createRedirectResponse($this->admin_userRoleRepository->getDefinition(), $roleId, $request, $context);
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
        $this->validateScope($request);

        $this->roleRepository->delete([['id' => $roleId]], $context);

        return $factory->createRedirectResponse($this->roleRepository->getDefinition(), $roleId, $request, $context);
    }

    /**
     * A self-service profile edit may link an avatar, nothing more. The write runs in SYSTEM_SCOPE,
     * where ACL and write protection are off, so any other field would be written to the media
     * entity and, through its associations (admin_user, avatarUsers), to other users.
     */
    private function assertAvatarMediaIsLinkOnly(mixed $avatarMedia): void
    {
        if (!\is_array($avatarMedia) || array_keys($avatarMedia) !== ['id'] || !\is_string($avatarMedia['id'])) {
            throw ApiException::missingPrivileges(['admin_user:update']);
        }
    }

    private function validateScope(Request $request): void
    {
        // only validate scope for administration clients
        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID) !== 'administration') {
            return;
        }

        if ($this->ssoService->isSso()) {
            return;
        }

        if (!$this->hasScope($request)) {
            throw ApiException::invalidScopeAccessToken(AdminUserVerifiedScope::IDENTIFIER);
        }
    }

    private function hasScope(Request $request): bool
    {
        $scopes = array_flip($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES));

        return isset($scopes[AdminUserVerifiedScope::IDENTIFIER]);
    }
}
