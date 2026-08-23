<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\ApiDefinition\Generator;

use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\PlatformRequest;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 *
 * Routes can opt out of generated OpenAPI output by setting the Symfony route default
 * `_openapi` to the strict boolean `false`. The route remains available at runtime;
 * only the generated API schema omits the matching path operation.
 *
 * @phpstan-import-type Api from DefinitionService
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
class OpenApiRouteDefaultsFilter
{
    private const array OPEN_API_METHODS = [
        'delete' => true,
        'get' => true,
        'head' => true,
        'options' => true,
        'patch' => true,
        'post' => true,
        'put' => true,
        'trace' => true,
    ];

    public function __construct(private readonly RouterInterface $router)
    {
    }

    /**
     * @param OpenApiSpec $spec
     * @param Api $api
     *
     * @return OpenApiSpec
     */
    public function filter(array $spec, string $api): array
    {
        $hiddenPaths = $this->getHiddenOpenApiPaths($api);

        if ($hiddenPaths === []) {
            return $spec;
        }

        foreach (array_keys($spec['paths']) as $path) {
            if (!isset($hiddenPaths[$path])) {
                continue;
            }

            if (isset($hiddenPaths[$path]['*'])) {
                unset($spec['paths'][$path]);

                continue;
            }

            foreach (array_keys($hiddenPaths[$path]) as $method) {
                unset($spec['paths'][$path][$method]);
            }

            if (!$this->hasOpenApiOperation($spec['paths'][$path])) {
                unset($spec['paths'][$path]);
            }
        }

        return $spec;
    }

    /**
     * @param Api $api
     *
     * @return array<string, array<string, true>>
     */
    private function getHiddenOpenApiPaths(string $api): array
    {
        $apiPrefix = match ($api) {
            DefinitionService::API => '/api',
            DefinitionService::CHANNEL_API => '/channel-api',
        };

        $hiddenPaths = [];

        foreach ($this->router->getRouteCollection()->all() as $route) {
            if ($route->getDefault(PlatformRequest::ATTRIBUTE_OPENAPI) !== false) {
                continue;
            }

            $path = $route->getPath();
            if (!str_starts_with($path, $apiPrefix)) {
                continue;
            }

            $openApiPath = \substr($path, \strlen($apiPrefix)) ?: '/';
            $methods = $route->getMethods();

            if ($methods === []) {
                $hiddenPaths[$openApiPath]['*'] = true;

                continue;
            }

            foreach ($methods as $method) {
                $hiddenPaths[$openApiPath][strtolower($method)] = true;
            }
        }

        return $hiddenPaths;
    }

    /**
     * @param array<string, mixed> $pathItem
     */
    private function hasOpenApiOperation(array $pathItem): bool
    {
        foreach (array_keys($pathItem) as $key) {
            if (\is_string($key) && isset(self::OPEN_API_METHODS[strtolower($key)])) {
                return true;
            }
        }

        return false;
    }
}
