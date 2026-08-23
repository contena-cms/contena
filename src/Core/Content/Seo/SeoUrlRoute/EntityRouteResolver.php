<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Defaults;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class EntityRouteResolver
{
    /**
     * @internal
     *
     * @param iterable<EntitySeoUrlRouteInterface> $channelApiSeoUrlRoutes
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $registry,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly RouterInterface $router,
        private readonly iterable $channelApiSeoUrlRoutes = [],
    ) {
    }

    public function getRouteNameForEntityName(string $entityName, ?string $channelTypeId = null): string
    {
        return $this->getRouteConfig($entityName, $channelTypeId)->getRouteName();
    }

    /**
     * Generates a SEO URL placeholder for the given entity.
     * Returns the Channel API route when no frontend route is registered for the entity type.
     */
    public function generateSeoUrlPlaceholder(string $entityName, string $primaryKey, ?string $channelTypeId = null): string
    {
        $config = $this->getRouteConfig($entityName, $channelTypeId);

        return $this->seoUrlPlaceholderHandler->generate($config->getRouteName(), $config->getPrimaryKeyParameter($primaryKey));
    }

    /**
     * Generates a concrete URL for the given entity via the Symfony router.
     * Returns the Channel API route when no frontend route is registered for the entity type.
     */
    public function generateUrl(string $entityName, string $primaryKey, ?string $channelTypeId = null): string
    {
        $config = $this->getRouteConfig($entityName, $channelTypeId);

        return $this->router->generate($config->getRouteName(), $config->getPrimaryKeyParameter($primaryKey));
    }

    public function findEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        foreach ($this->channelApiSeoUrlRoutes as $entitySeoUrlRoute) {
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
        string $channelTypeId,
    ): array {
        try {
            $routeNameByEntity = $this->getRouteNameForEntityName($entityName, $channelTypeId);
        } catch (SeoUrlRouteConfigException) {
            return [];
        }

        if ($routeNameByEntity === $routeName) {
            return [];
        }

        return [
            'routeName' => $routeNameByEntity,
            'pathInfo' => $this->generatePathInfo($entityName, $primaryKey, $channelTypeId),
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
        foreach ($this->channelApiSeoUrlRoutes as $channelApiSeoUrlRoute) {
            $config = $channelApiSeoUrlRoute->getConfig();

            if ($config->getDefinition()->getEntityName() === $entityName) {
                return $config;
            }
        }

        throw SeoUrlRouteConfigException::routeConfigNotFoundForEntityName($entityName);
    }
}
