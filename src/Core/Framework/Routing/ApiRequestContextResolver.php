<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Tenant\Resolver\TenantResolution;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

class ApiRequestContextResolver implements RequestContextResolverInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public function resolve(Request $request): void
    {
        if ($request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)) {
            return;
        }

        if (!$this->isRequestScoped($request, ApiContextRouteScopeDependant::class)) {
            return;
        }

        $params = $this->getContextParameters($request);
        $languageIdChain = $this->getLanguageIdChain($params);

        $context = new Context(
            source: $this->resolveContextSource($request),
            languageIdChain: $languageIdChain,
            versionId: $params['versionId'] ?? Defaults::LIVE_VERSION,
            considerInheritance: $params['considerInheritance'],
        );

        $this->resolveTenantScope($context, $request);

        if ($context->getSource() instanceof AdminApiSource) {
            $this->refreshAdminApiSource($context->getSource(), $context->getTenantId());
        }

        if ($request->headers->has(PlatformRequest::HEADER_SKIP_TRIGGER_FLOW)) {
            $skipTriggerFlow = filter_var($request->headers->get(PlatformRequest::HEADER_SKIP_TRIGGER_FLOW, 'false'), \FILTER_VALIDATE_BOOLEAN);

            if ($skipTriggerFlow) {
                $context->addState(Context::SKIP_TRIGGER_FLOW);
            }
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * @return array{languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string, versionId: ?string, considerInheritance: bool}
     */
    private function getContextParameters(Request $request): array
    {
        $params = [
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'systemFallbackLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'versionId' => $request->headers->get(PlatformRequest::HEADER_VERSION_ID),
            'considerInheritance' => false,
        ];

        $runtimeParams = $this->getRuntimeParameters($request);

        /** @var array{languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string, versionId: ?string, considerInheritance: bool} $params */
        $params = array_replace_recursive($params, $runtimeParams);

        return $params;
    }

    /**
     * @return array{languageId?: string, considerInheritance?: true}
     */
    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [];

        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '');
        if ($languageId !== '') {
            $parameters['languageId'] = $languageId;
        }

        if ($request->headers->has(PlatformRequest::HEADER_INHERITANCE)) {
            $parameters['considerInheritance'] = true;
        }

        return $parameters;
    }

    private function resolveContextSource(Request $request): ContextSource
    {
        if ($channelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID)) {
            return new ChannelApiSource((string) $channelId);
        }

        if ($userId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID)) {
            return $this->getAdminApiSource($userId);
        }

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)) {
            return new SystemSource();
        }

        $clientId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        $keyOrigin = AccessKeyHelper::getOrigin($clientId);

        if ($keyOrigin === 'user') {
            $userId = $this->getUserIdByAccessKey($clientId);

            return $this->getAdminApiSource($userId);
        }

        if ($keyOrigin === 'integration') {
            $integrationId = $this->getIntegrationIdByAccessKey($clientId);

            return $this->getAdminApiSource(null, $integrationId);
        }

        return new SystemSource();
    }

    /**
     * @param array{languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string} $params
     *
     * @return non-empty-list<string>
     */
    private function getLanguageIdChain(array $params): array
    {
        $languageId = $params['languageId'];
        if ($languageId === Defaults::LANGUAGE_SYSTEM) {
            // no query needed
            return [$languageId];
        }

        return array_values(array_filter([$languageId, $this->getParentLanguageId($languageId), $params['systemFallbackLanguageId']]));
    }

    private function getParentLanguageId(?string $languageId): ?string
    {
        if ($languageId === null || !Uuid::isValid($languageId)) {
            throw RoutingException::languageNotFound($languageId);
        }
        $data = $this->connection->createQueryBuilder()
            ->select('LOWER(HEX(language.parent_id))')
            ->from('language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->executeQuery()
            ->fetchFirstColumn();

        if ($data === []) {
            throw RoutingException::languageNotFound($languageId);
        }

        return $data[0];
    }

    private function getUserIdByAccessKey(string $clientId): string
    {
        $id = $this->connection->createQueryBuilder()
            ->select('user_id')
            ->from('user_access_key')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientId)
            ->executeQuery()
            ->fetchOne();

        return Uuid::fromBytesToHex($id);
    }

    private function getIntegrationIdByAccessKey(string $clientId): string
    {
        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('integration')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientId)
            ->executeQuery()
            ->fetchOne();

        return Uuid::fromBytesToHex($id);
    }

    private function getAdminApiSource(?string $userId, ?string $integrationId = null): AdminApiSource
    {
        return new AdminApiSource($userId, $integrationId);
    }

    private function refreshAdminApiSource(AdminApiSource $source, ?string $tenantId): void
    {
        if ($source->getUserId() !== null) {
            $userId = $source->getUserId();
            $source->setPermissions($this->withDefaultUserPrivileges($this->fetchPermissions($userId, $tenantId)));
            $source->setIsAdmin($this->isAdmin($userId, $tenantId));
        }

        if ($source->getIntegrationId() !== null) {
            $integrationId = $source->getIntegrationId();
            $source->setIsAdmin($this->isAdminIntegration($integrationId));
            $source->setPermissions($this->fetchIntegrationPermissions($integrationId, $tenantId));
        }
    }

    /**
     * @param array<string> $permissions
     *
     * @return array<string>
     */
    private function withDefaultUserPrivileges(array $permissions): array
    {
        return array_values(array_unique([
            ...$permissions,
            ...AdminApiSource::DEFAULT_USER_PRIVILEGES,
        ]));
    }

    private function isAdmin(string $userId, ?string $tenantId = null): bool
    {
        if ($tenantId !== null && $this->hasUserMembership($userId)) {
            return (bool) $this->connection->fetchOne(
                'SELECT admin FROM `user_tenant` WHERE user_id = :userId AND tenant_id = :tenantId AND active = 1',
                [
                    'userId' => Uuid::fromHexToBytes($userId),
                    'tenantId' => Uuid::fromHexToBytes($tenantId),
                ],
            );
        }

        return (bool) $this->connection->fetchOne(
            'SELECT admin FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );
    }

    private function isAdminIntegration(string $integrationId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT admin FROM `integration` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($integrationId)]
        );
    }

    /**
     * @return string[]
     */
    private function fetchPermissions(string $userId, ?string $tenantId = null): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('acl_user_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
        ;
        if ($tenantId !== null) {
            $permissions->andWhere('mapping.tenant_id = :tenantId')
                ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        } else {
            $permissions->andWhere('mapping.tenant_id IS NULL');
        }
        $permissions = $permissions->executeQuery()->fetchFirstColumn();

        $list = [];
        foreach ($permissions as $privileges) {
            $privileges = json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
            $list = array_merge($list, $privileges);
        }

        return array_unique(array_filter($list));
    }

    /**
     * @return string[]
     */
    private function fetchIntegrationPermissions(string $integrationId, ?string $tenantId = null): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('integration_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.integration_id = :integrationId')
            ->setParameter('integrationId', Uuid::fromHexToBytes($integrationId));
        if ($tenantId !== null) {
            $permissions->andWhere('mapping.tenant_id = :tenantId')
                ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        } else {
            $permissions->andWhere('mapping.tenant_id IS NULL');
        }
        $permissions = $permissions->executeQuery()->fetchFirstColumn();

        $list = [];
        foreach ($permissions as $privileges) {
            $privileges = json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
            $list = array_merge($list, $privileges);
        }

        return array_unique(array_filter($list));
    }

    /**
     * Binds the context to a tenant: authenticated tenant users
     * are bound to their tenant, platform users get global cross-tenant access
     * and may switch into a tenant via the ct-tenant-id header, and channel
     * sources inherit the tenant of their channel.
     */
    private function resolveTenantScope(Context $context, Request $request): void
    {
        $source = $context->getSource();

        if ($source instanceof AdminApiSource) {
            $this->resolveAdminTenantScope($context, $request, $source);

            return;
        }

        if ($source instanceof ChannelApiSource) {
            $tenantId = $this->connection->fetchOne(
                'SELECT LOWER(HEX(`tenant_id`)) FROM `channel` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($source->getChannelId())],
            );

            if ($tenantId) {
                $context->setTenantId($tenantId);
            }
        }
    }

    private function resolveAdminTenantScope(Context $context, Request $request, AdminApiSource $source): void
    {
        $userId = $source->getUserId();
        $integrationId = $source->getIntegrationId();
        if ($userId === null && $integrationId === null) {
            return;
        }

        if ($userId !== null) {
            $this->resolveUserTenantScope($context, $request, $userId);

            return;
        }

        $ownerTenantId = $this->fetchOwnerTenantId('integration', (string) $integrationId);

        $requestedTenantId = $request->headers->get(PlatformRequest::HEADER_TENANT_ID);

        $resolution = $request->attributes->get(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID);
        $resolvedTenantId = $resolution instanceof TenantResolution ? $resolution->tenantId : null;

        if ($ownerTenantId !== null) {
            // Tenant actors are bound to their tenant and can not switch.
            if ($resolvedTenantId !== null) {
                // On a tenant-bound domain the domain itself is the tenant's
                // address; mismatches are rejected.
                if ($resolvedTenantId !== $ownerTenantId) {
                    throw RoutingException::tenantDomainMismatch();
                }
            } elseif ($requestedTenantId !== $ownerTenantId) {
                throw RoutingException::tenantSwitchForbidden();
            }

            $context->setTenantId($ownerTenantId);

            return;
        }

        // Platform users read across all tenants by default, may switch into
        // one via the header, and default to the tenant of the current domain.
        if ($requestedTenantId) {
            $context->setTenantId($requestedTenantId);

            return;
        }

        if ($resolvedTenantId) {
            $context->setTenantId($resolvedTenantId);

            return;
        }

        $context->setGlobalTenantAccess(true);
    }

    private function resolveUserTenantScope(Context $context, Request $request, string $userId): void
    {
        $memberships = $this->fetchUserMemberships($userId);
        $requestedTenantId = $request->headers->get(PlatformRequest::HEADER_TENANT_ID);
        $resolution = $request->attributes->get(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID);
        $resolvedTenantId = $resolution instanceof TenantResolution ? $resolution->tenantId : null;

        if ($requestedTenantId !== null && $resolvedTenantId !== null && $requestedTenantId !== $resolvedTenantId) {
            throw RoutingException::tenantDomainMismatch();
        }

        if ($memberships !== []) {
            $targetTenantId = $resolvedTenantId ?? $requestedTenantId;
            if ($targetTenantId === null || !($memberships[$targetTenantId] ?? false)) {
                throw $resolvedTenantId !== null
                    ? RoutingException::tenantDomainMismatch()
                    : RoutingException::tenantSwitchForbidden();
            }

            $context->setTenantId($targetTenantId);

            return;
        }

        if ($requestedTenantId !== null) {
            $context->setTenantId($requestedTenantId);

            return;
        }

        if ($resolvedTenantId !== null) {
            $context->setTenantId($resolvedTenantId);

            return;
        }

        $context->setGlobalTenantAccess(true);
    }

    /**
     * @return array<string, bool>
     */
    private function fetchUserMemberships(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(tenant_id)) AS tenant_id, active FROM user_tenant WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)],
        );

        $memberships = [];
        foreach ($rows as $row) {
            $memberships[(string) $row['tenant_id']] = (bool) $row['active'];
        }

        return $memberships;
    }

    private function hasUserMembership(string $userId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM user_tenant WHERE user_id = :userId LIMIT 1',
            ['userId' => Uuid::fromHexToBytes($userId)],
        );
    }

    private function fetchOwnerTenantId(string $table, string $ownerId): ?string
    {
        $tenantId = $this->connection->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `id` = :id', $table),
            ['id' => Uuid::fromHexToBytes($ownerId)],
        );

        return \is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
