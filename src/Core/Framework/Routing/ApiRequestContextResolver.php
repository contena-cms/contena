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
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\Routing\RouteScopeCheckTrait;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Framework\Routing\ApiRequestContextResolverTest
 */
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

        $rounding = $this->getCashRounding($params['currencyId']);

        $context = new Context(
            $this->resolveContextSource($request),
            [],
            $params['currencyId'],
            $languageIdChain,
            $params['versionId'] ?? Defaults::LIVE_VERSION,
            $params['currencyFactory'],
            $params['considerInheritance'],
            Context::TAX_STATE_GROSS,
            $rounding
        );

        if ($request->headers->has(PlatformRequest::HEADER_SKIP_TRIGGER_FLOW)) {
            $skipTriggerFlow = filter_var($request->headers->get(PlatformRequest::HEADER_SKIP_TRIGGER_FLOW, 'false'), \FILTER_VALIDATE_BOOLEAN);

            if ($skipTriggerFlow) {
                $context->addState(Context::SKIP_TRIGGER_FLOW);
            }
        }

        $indexingBehavior = $request->headers->get(PlatformRequest::HEADER_INDEXING_BEHAVIOR);
        if (\in_array($indexingBehavior, [EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE], true)) {
            $context->addState($indexingBehavior);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * @return array{currencyId: string, languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string, currencyFactory: float, currencyPrecision: int, versionId: ?string, considerInheritance: bool}
     */
    private function getContextParameters(Request $request): array
    {
        $params = [
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'systemFallbackLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyFactory' => 1.0,
            'currencyPrecision' => 2,
            'versionId' => $request->headers->get(PlatformRequest::HEADER_VERSION_ID),
            'considerInheritance' => false,
        ];

        $runtimeParams = $this->getRuntimeParameters($request);

        /** @var array{currencyId: string, languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string, currencyFactory: float, currencyPrecision: int, versionId: ?string, considerInheritance: bool} $params */
        $params = array_replace_recursive($params, $runtimeParams);

        return $params;
    }

    /**
     * @return array{languageId?: string, currencyId?: string, considerInheritance?: true}
     */
    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [];

        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '');
        if ($languageId !== '') {
            $parameters['languageId'] = $languageId;
        }

        $currencyId = $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID, '');
        if ($currencyId !== '') {
            $parameters['currencyId'] = $currencyId;
        }

        if ($request->headers->has(PlatformRequest::HEADER_INHERITANCE)) {
            $parameters['considerInheritance'] = true;
        }

        return $parameters;
    }

    private function resolveContextSource(Request $request): ContextSource
    {
        if ($adminUserId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID)) {
            $appIntegrationId = strtolower((string) $request->headers->get(PlatformRequest::HEADER_APP_INTEGRATION_ID, ''));

            // The app integration id header is only to be used by a privileged admin user
            if ($appIntegrationId !== '' && $this->adminUserAppIntegrationHeaderPrivileged($adminUserId, $appIntegrationId)) {
                $adminUserId = null;
            } else {
                $appIntegrationId = null;
            }

            return $this->getAdminApiSource($adminUserId, $appIntegrationId);
        }

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)) {
            return new SystemSource();
        }

        $clientId = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        $keyOrigin = AccessKeyHelper::getOrigin($clientId);

        if ($keyOrigin === 'admin_user') {
            $adminUserId = $this->getUserIdByAccessKey($clientId);

            return $this->getAdminApiSource($adminUserId);
        }

        if ($keyOrigin === 'integration') {
            $integrationId = $this->getIntegrationIdByAccessKey($clientId);

            $adminUserId = $request->headers->get(PlatformRequest::HEADER_APP_USER_ID, '');
            if ($adminUserId === '' || !Uuid::isValid((string) $adminUserId)) {
                $adminUserId = null;
            }

            if ($adminUserId !== null && $this->isAppIntegration($integrationId) && !$this->adminUserAppIntegrationHeaderPrivileged($adminUserId, $integrationId)) {
                $adminUserId = null;
            }

            return $this->getAdminApiSource($adminUserId, $integrationId);
        }

        if ($keyOrigin === 'sales-channel') {
            $channelId = $this->getChannelIdByAccessKey($clientId);

            return new ChannelApiSource($channelId);
        }

        return new SystemSource();
    }

    /**
     * @param array{currencyId: string, languageId: non-falsy-string, systemFallbackLanguageId: non-falsy-string, currencyFactory: float, currencyPrecision: int, versionId: ?string, considerInheritance: bool} $params
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
            ->select('admin_user_id')
            ->from('admin_user_access_key')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientId)
            ->executeQuery()
            ->fetchOne();

        return Uuid::fromBytesToHex($id);
    }

    private function getChannelIdByAccessKey(string $clientId): string
    {
        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('channel')
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

    private function getAdminApiSource(?string $adminUserId, ?string $integrationId = null): AdminApiSource
    {
        $source = new AdminApiSource($adminUserId, $integrationId);

        // Use the permissions associated to that app, if the request is made by an integration associated to an app
        $appPermissions = $this->fetchPermissionsIntegrationByApp($integrationId);
        if ($appPermissions !== null) {
            // If both adminUserId and integrationId are provided (HEADER_APP_USER_ID case), intersect admin user permissions with app permissions
            if ($adminUserId !== null && !$this->isAdmin($adminUserId)) {
                $appPermissions = array_intersect(
                    $appPermissions,
                    $this->fetchPermissions($adminUserId)
                );
            }

            $source->setIsAdmin(false);
            $source->setPermissions($appPermissions);

            return $source;
        }

        if ($adminUserId !== null && $integrationId !== null) {
            if ($this->isAdminIntegration($integrationId)) {
                $source->setPermissions($this->withDefaultUserPrivileges($this->fetchPermissions($adminUserId)));
                $source->setIsAdmin($this->isAdmin($adminUserId));

                return $source;
            }

            $permissions = $this->fetchIntegrationPermissions($integrationId);

            if (!$this->isAdmin($adminUserId)) {
                $permissions = array_intersect(
                    $permissions,
                    $this->fetchPermissions($adminUserId)
                );
            }

            $source->setIsAdmin(false);
            $source->setPermissions($permissions);

            return $source;
        }

        if ($adminUserId !== null) {
            $source->setPermissions($this->withDefaultUserPrivileges($this->fetchPermissions($adminUserId)));
            $source->setIsAdmin($this->isAdmin($adminUserId));

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

    private function isAdmin(string $adminUserId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT admin FROM `admin_user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($adminUserId)]
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
    private function fetchPermissions(string $adminUserId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select('role.privileges')
            ->from('acl_user_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.admin_user_id = :adminUserId')
            ->setParameter('adminUserId', Uuid::fromHexToBytes($adminUserId))
            ->executeQuery()
            ->fetchFirstColumn();

        $list = [];
        foreach ($permissions as $privileges) {
            $privileges = json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
            $list = array_merge($list, $privileges);
        }

        return array_unique(array_filter($list));
    }

    private function getCashRounding(string $currencyId): CashRoundingConfig
    {
        $rounding = $this->connection->fetchAssociative(
            'SELECT item_rounding FROM currency WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($currencyId)]
        );
        if ($rounding === false) {
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw new \RuntimeException(\sprintf('No cash rounding for currency "%s" found', $currencyId));
            }
            throw RoutingException::currencyNotFound($currencyId);
        }

        $rounding = json_decode((string) $rounding['item_rounding'], true, 512, \JSON_THROW_ON_ERROR);

        return new CashRoundingConfig(
            (int) $rounding['decimals'],
            (float) $rounding['interval'],
            (bool) $rounding['roundForNet']
        );
    }

    /**
     * @return string[]|null
     */
    private function fetchPermissionsIntegrationByApp(?string $integrationId): ?array
    {
        if (!$integrationId) {
            return null;
        }

        $privileges = $this->connection->fetchOne('
            SELECT `acl_role`.`privileges`
            FROM `acl_role`
            INNER JOIN `app` ON `app`.`acl_role_id` = `acl_role`.`id`
            WHERE `app`.`integration_id` = :integrationId
        ', ['integrationId' => Uuid::fromHexToBytes($integrationId)]);

        if ($privileges === false) {
            return null;
        }

        return json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);
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

    private function fetchAppNameByIntegrationId(string $integrationId): ?string
    {
        $name = $this->connection->createQueryBuilder()
            ->select('app.name')
            ->from('app', 'app')
            ->innerJoin('app', 'integration', 'integration', 'integration.id = app.integration_id')
            ->where('integration.id = :integrationId')
            ->andWhere('app.active = 1')
            ->setParameter('integrationId', Uuid::fromHexToBytes($integrationId))
            ->executeQuery()
            ->fetchOne();

        if ($name === false) {
            return null;
        }

        return $name;
    }

    private function isAppIntegration(string $integrationId): bool
    {
        return $this->fetchAppNameByIntegrationId($integrationId) !== null;
    }

    /**
     * @throws RoutingException
     */
    private function adminUserAppIntegrationHeaderPrivileged(string $adminUserId, ?string $appIntegrationId): bool
    {
        if ($appIntegrationId === null) {
            return false;
        }

        $appName = $this->fetchAppNameByIntegrationId($appIntegrationId);
        if ($appName === null) {
            throw RoutingException::appIntegrationNotFound($appIntegrationId);
        }

        if ($this->isAdmin($adminUserId)) {
            return true;
        }

        $permissions = $this->fetchPermissions($adminUserId);
        $allAppsPrivileged = \in_array('app.all', $permissions, true);
        $appPrivilegeName = \sprintf('app.%s', $appName);
        $specificAppPrivileged = \in_array($appPrivilegeName, $permissions, true);

        if (!($specificAppPrivileged || $allAppsPrivileged)) {
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw new MissingPrivilegeException([$appPrivilegeName]);
            }
            throw RoutingException::missingPrivileges([$appPrivilegeName]);
        }

        return true;
    }
}
