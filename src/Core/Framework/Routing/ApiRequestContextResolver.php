<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
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
        $source = new AdminApiSource($userId, $integrationId);

        if ($userId !== null) {
            $source->setPermissions($this->withDefaultUserPrivileges($this->fetchPermissions($userId)));
            $source->setIsAdmin($this->isAdmin($userId));

            return $source;
        }

        if ($integrationId !== null) {
            $source->setIsAdmin($this->isAdminIntegration($integrationId));
            $source->setPermissions($this->fetchIntegrationPermissions($integrationId));

            return $source;
        }

        return $source;
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

    private function isAdmin(string $userId): bool
    {
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
    private function fetchPermissions(string $userId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('acl_user_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchFirstColumn();

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
    private function fetchIntegrationPermissions(string $integrationId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('integration_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.integration_id = :integrationId')
            ->setParameter('integrationId', Uuid::fromHexToBytes($integrationId))
            ->executeQuery()
            ->fetchFirstColumn();

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

        $ownerTenantId = $userId !== null
            ? $this->fetchOwnerTenantId('user', $userId)
            : $this->fetchOwnerTenantId('integration', (string) $integrationId);

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

    private function fetchOwnerTenantId(string $table, string $ownerId): ?string
    {
        $tenantId = $this->connection->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `id` = :id', $table),
            ['id' => Uuid::fromHexToBytes($ownerId)],
        );

        return \is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
