<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataScope;
use Contena\Core\Framework\DataAbstractionLayer\DataScopeReadMode;
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

        $source = $this->resolveContextSource($request);
        [$dataScope, $dataScopeReadMode] = $this->resolveDataScope($source, $request);
        $context = new Context(
            source: $source,
            languageIdChain: $languageIdChain,
            versionId: $params['versionId'] ?? Defaults::LIVE_VERSION,
            considerInheritance: $params['considerInheritance'],
            dataScope: $dataScope,
            dataScopeReadMode: $dataScopeReadMode,
        );

        if ($context->getSource() instanceof AdminApiSource) {
            $this->refreshAdminApiSource($context->getSource(), $context->getDataScopeId());
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
     * @param array{languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string, versionId: ?string, considerInheritance: bool} $params
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

    private function refreshAdminApiSource(AdminApiSource $source, string $dataScopeId): void
    {
        if ($source->getUserId() !== null) {
            $userId = $source->getUserId();
            $source->setPermissions($this->withDefaultUserPrivileges($this->fetchPermissions($userId, $dataScopeId)));
            $source->setIsAdmin($this->isAdmin($userId, $dataScopeId));
        }

        if ($source->getIntegrationId() !== null) {
            $integrationId = $source->getIntegrationId();
            $source->setIsAdmin($this->isAdminIntegration($integrationId));
            $source->setPermissions($this->fetchIntegrationPermissions($integrationId, $dataScopeId));
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

    private function isAdmin(string $userId, string $dataScopeId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT admin FROM `user_data_scope` WHERE user_id = :userId AND data_scope_id = :dataScopeId AND active = 1',
            [
                'userId' => Uuid::fromHexToBytes($userId),
                'dataScopeId' => Uuid::fromHexToBytes($dataScopeId),
            ],
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
    private function fetchPermissions(string $userId, string $dataScopeId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('acl_user_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
        ;
        $permissions->andWhere('mapping.data_scope_id = :dataScopeId')
            ->setParameter('dataScopeId', Uuid::fromHexToBytes($dataScopeId));
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
    private function fetchIntegrationPermissions(string $integrationId, string $dataScopeId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('integration_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.integration_id = :integrationId')
            ->setParameter('integrationId', Uuid::fromHexToBytes($integrationId));
        $permissions->andWhere('mapping.data_scope_id = :dataScopeId')
            ->setParameter('dataScopeId', Uuid::fromHexToBytes($dataScopeId));
        $permissions = $permissions->executeQuery()->fetchFirstColumn();

        $list = [];
        foreach ($permissions as $privileges) {
            $privileges = json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
            $list = array_merge($list, $privileges);
        }

        return array_unique(array_filter($list));
    }

    /**
     * Resolves the exact write scope and independently grants cross-scope reads.
     * No actor gains authority from a missing grant or from the platform scope
     * type itself.
     *
     * @return array{DataScope, DataScopeReadMode}
     */
    private function resolveDataScope(ContextSource $source, Request $request): array
    {
        if ($source instanceof AdminApiSource) {
            return $this->resolveAdminDataScope($source, $request);
        }

        if ($source instanceof ChannelApiSource) {
            $dataScopeId = $this->fetchOwnerDataScopeId('channel', $source->getChannelId());
            $this->assertRequestedTenantMatchesScope($request, $dataScopeId);

            return [$this->dataScopeFromId($dataScopeId), DataScopeReadMode::Exact];
        }

        return [DataScope::platform(), DataScopeReadMode::Exact];
    }

    /**
     * @return array{DataScope, DataScopeReadMode}
     */
    private function resolveAdminDataScope(AdminApiSource $source, Request $request): array
    {
        if ($source->getUserId() !== null) {
            return $this->resolveUserDataScope($request, $source->getUserId());
        }

        if ($source->getIntegrationId() === null) {
            return [DataScope::platform(), DataScopeReadMode::Exact];
        }

        $dataScopeId = $this->fetchOwnerDataScopeId('integration', $source->getIntegrationId());
        $this->assertRequestedTenantMatchesScope($request, $dataScopeId);

        return [$this->dataScopeFromId($dataScopeId), DataScopeReadMode::Exact];
    }

    /**
     * @return array{DataScope, DataScopeReadMode}
     */
    private function resolveUserDataScope(Request $request, string $userId): array
    {
        $grants = $this->fetchUserDataScopeGrants($userId);
        [$targetTenantId, $resolvedFromDomain] = $this->resolveTargetTenant($request);

        if ($targetTenantId !== null) {
            if (!($grants[$targetTenantId]['active'] ?? false)) {
                throw $resolvedFromDomain
                    ? RoutingException::tenantDomainMismatch()
                    : RoutingException::tenantSwitchForbidden();
            }

            return [DataScope::tenant($targetTenantId), DataScopeReadMode::Exact];
        }

        $platformGrant = $grants[Defaults::PLATFORM_DATA_SCOPE] ?? null;
        if ($platformGrant === null || !$platformGrant['active']) {
            throw RoutingException::dataScopeAccessForbidden();
        }

        return [
            DataScope::platform(),
            $platformGrant['readAllScopes'] ? DataScopeReadMode::All : DataScopeReadMode::Exact,
        ];
    }

    /**
     * @return array<string, array{active: bool, readAllScopes: bool}>
     */
    private function fetchUserDataScopeGrants(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT LOWER(HEX(scope_grant.data_scope_id)) AS data_scope_id,
       scope_grant.active,
       scope_grant.read_all_scopes
FROM user_data_scope scope_grant
INNER JOIN `user` user_identity ON user_identity.id = scope_grant.user_id
WHERE scope_grant.user_id = :userId AND user_identity.active = 1
SQL,
            ['userId' => Uuid::fromHexToBytes($userId)],
        );

        $grants = [];
        foreach ($rows as $row) {
            $grants[(string) $row['data_scope_id']] = [
                'active' => (bool) $row['active'],
                'readAllScopes' => (bool) $row['read_all_scopes'],
            ];
        }

        return $grants;
    }

    /**
     * @return array{?string, bool}
     */
    private function resolveTargetTenant(Request $request): array
    {
        $requestedTenantId = $request->headers->get(PlatformRequest::HEADER_TENANT_ID);
        $requestedTenantId = \is_string($requestedTenantId) && $requestedTenantId !== '' ? $requestedTenantId : null;
        if ($requestedTenantId !== null && !Uuid::isValid($requestedTenantId)) {
            throw RoutingException::invalidRequestParameter(PlatformRequest::HEADER_TENANT_ID);
        }

        $resolution = $request->attributes->get(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID);
        $resolvedTenantId = $resolution instanceof TenantResolution ? $resolution->tenantId : null;

        if ($requestedTenantId !== null && $resolvedTenantId !== null && $requestedTenantId !== $resolvedTenantId) {
            throw RoutingException::tenantDomainMismatch();
        }

        return [$resolvedTenantId ?? $requestedTenantId, $resolvedTenantId !== null];
    }

    private function assertRequestedTenantMatchesScope(Request $request, string $dataScopeId): void
    {
        [$targetTenantId, $resolvedFromDomain] = $this->resolveTargetTenant($request);
        if ($targetTenantId === null) {
            return;
        }

        if ($targetTenantId !== $dataScopeId) {
            throw $resolvedFromDomain
                ? RoutingException::tenantDomainMismatch()
                : RoutingException::tenantSwitchForbidden();
        }
    }

    private function fetchOwnerDataScopeId(string $table, string $ownerId): string
    {
        $dataScopeId = $this->connection->fetchOne(
            \sprintf('SELECT LOWER(HEX(`data_scope_id`)) FROM `%s` WHERE `id` = :id', $table),
            ['id' => Uuid::fromHexToBytes($ownerId)],
        );

        if (!\is_string($dataScopeId) || !Uuid::isValid($dataScopeId)) {
            throw RoutingException::dataScopeAccessForbidden();
        }

        return $dataScopeId;
    }

    private function dataScopeFromId(string $dataScopeId): DataScope
    {
        return $dataScopeId === Defaults::PLATFORM_DATA_SCOPE
            ? DataScope::platform()
            : DataScope::tenant($dataScopeId);
    }
}
