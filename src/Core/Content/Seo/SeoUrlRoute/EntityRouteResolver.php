<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class EntityRouteResolver
{
    /**
     * @param iterable<EntitySeoUrlRouteInterface> $storeApiSeoUrlRoutes
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $registry,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly RouterInterface $router,
        private readonly iterable $storeApiSeoUrlRoutes = [],
    ) {
    }

    public function getRouteNameForEntityName(string $entityName, ?string $channelTypeId = null): string
    {
        return $this->getRouteConfig($entityName, $channelTypeId)->getRouteName();
    }

    /**
     * Generates a SEO URL placeholder for the given entity.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     */
    public function generateSeoUrlPlaceholder(string $entityName, string $primaryKey, ?string $channelTypeId = null): string
    {
        $config = $this->getRouteConfig($entityName, $channelTypeId);

        return $this->seoUrlPlaceholderHandler->generate($config->getTargetRouteName(), $config->getPrimaryKeyParameter($primaryKey));
    }

    /**
     * Generates a concrete URL for the given entity via the Symfony router.
     * Returns store-api route when no route is registered for the entity type (e.g. headless setups).
     */
    public function generateUrl(string $entityName, string $primaryKey, ?string $channelTypeId = null): string
    {
        $config = $this->getRouteConfig($entityName, $channelTypeId);

        return $this->router->generate($config->getTargetRouteName(), $config->getPrimaryKeyParameter($primaryKey));
    }

    public function findEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        foreach ($this->storeApiSeoUrlRoutes as $entitySeoUrlRoute) {
            if ($entitySeoUrlRoute->getConfig()->getRouteName() === $routeName) {
                return $entitySeoUrlRoute;
            }
        }

        return null;
    }

    /**
     * @return array{routeName?: string, pathInfo?: string}
     */
    public function getSeoUrlRouteNameAndPathInfo(
        string $entityName,
        string $routeName,
        string $primaryKey,
        string $channelTypeId
    ): array {
        $belongsToFamily = $channelTypeId === Defaults::CHANNEL_TYPE_API
            ? $this->findEntitySeoUrlRoute($routeName) !== null
            : $this->registry->findByRouteName($routeName) !== null;

        if ($belongsToFamily) {
            return [];
        }

        try {
            $routeNameByEntity = $this->getRouteNameForEntityName(
                $entityName,
                $channelTypeId
            );
        } catch (SeoUrlRouteConfigException) {
            return [];
        }

        if ($routeNameByEntity === $routeName) {
            return [];
        }

        return [
            'routeName' => $routeNameByEntity,
            'pathInfo' => $this->generatePathInfo(
                $entityName,
                $primaryKey,
                $channelTypeId
            ),
        ];
    }

    private function generatePathInfo(string $entityName, string $primaryKey, string $channelTypeId): string
    {
        $url = $this->generateUrl($entityName, $primaryKey, $channelTypeId);
        $baseUrl = $this->router->getContext()->getBaseUrl();

        if ($baseUrl !== '') {
            $url = mb_substr($url, mb_strlen($baseUrl));
        }

        return $url;
    }

    private function getRouteConfig(string $entityName, ?string $channelTypeId = null): SeoUrlRouteConfig
    {
        if ($channelTypeId === Defaults::CHANNEL_TYPE_API) {
            try {
                return $this->getEntitySeoUrlRouteConfig($entityName);
            } catch (SeoUrlRouteConfigException) {
            }
        }

        $route = array_first($this->registry->findByDefinition($entityName));

        if ($route instanceof EntitySeoUrlRouteInterface) {
            return $route->getConfig();
        }

        return $this->getEntitySeoUrlRouteConfig($entityName);
    }

    private function getEntitySeoUrlRouteConfig(string $entityName): SeoUrlRouteConfig
    {
        foreach ($this->storeApiSeoUrlRoutes as $storeApiSeoUrlRoute) {
            $config = $storeApiSeoUrlRoute->getConfig();

            if ($config->getDefinition()->getEntityName() === $entityName) {
                return $config;
            }
        }

        throw SeoUrlRouteConfigException::routeConfigNotFoundForEntityName($entityName);
    }
}
