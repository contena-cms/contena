<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\Routing\ContentRouteDefinition;
use Contena\Core\Framework\ContentSystem\Channel\Routing\ContentRouteLoader;
use Contena\Core\Framework\ContentSystem\ContentPipeline;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class ContentRouteCompilerPass implements CompilerPassInterface
{
    private const FORMAT_FULL = 'full';
    private const SERVICE_ID_ROUTE_PATTERN = ContentRoute::class . '.%s.%s';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ContentRouteLoader::class)) {
            return;
        }

        $resolverTags = $container->findTaggedServiceIds('content_system.section_resolver');
        $formatTags = $container->findTaggedServiceIds('content_system.output_format');

        $routeDefinitions = [];

        foreach ($resolverTags as $resolverId => $sectionTagAttributes) {
            $section = ContentSection::from($sectionTagAttributes[0]['section']);
            $sectionName = $section->value;
            $pathSegment = $section->routePathSegment();

            foreach ($formatTags as $formatHandlerId => $formatTagAttributes) {
                $formatName = $formatTagAttributes[0]['format'];

                $serviceId = \sprintf(self::SERVICE_ID_ROUTE_PATTERN, $sectionName, $formatName);

                $routeServiceDefinition = new Definition(ContentRoute::class);
                $routeServiceDefinition->setPublic(true);
                $routeServiceDefinition->setArguments([
                    new Reference($resolverId),
                    $section,
                    new Reference(CacheTagCollector::class),
                    new Reference('content_layout.repository'),
                    new Reference($formatHandlerId),
                    new Reference(ContentPipeline::class),
                    new Reference(CacheFinalizer::class),
                ]);

                $container->setDefinition($serviceId, $routeServiceDefinition);

                $routeDefinitions[] = $this->buildRouteDefinition(
                    $serviceId,
                    $sectionName,
                    $formatName,
                    $pathSegment,
                );
            }
        }

        $container->getDefinition(ContentRouteLoader::class)->setArgument(0, array_map(
            static fn (ContentRouteDefinition $d) => $d->toDefinition(),
            $routeDefinitions,
        ));

        $container->setAlias(
            AbstractContentRoute::class,
            \sprintf(self::SERVICE_ID_ROUTE_PATTERN, ContentSection::MAIN->value, self::FORMAT_FULL),
        )->setPublic(true);
    }

    private function buildRouteDefinition(
        string $serviceId,
        string $sectionName,
        string $formatName,
        string $pathSegment,
    ): ContentRouteDefinition {
        $routeName = $this->buildRouteName($sectionName, $formatName, $pathSegment);
        $hasPath = $sectionName === ContentSection::MAIN->value;

        $urlSegment = $formatName === self::FORMAT_FULL
            ? $pathSegment
            : \sprintf('%s-%s', $pathSegment, $formatName);

        $path = $hasPath
            ? \sprintf('/channel-api/%s/{path}', $urlSegment)
            : \sprintf('/channel-api/%s', $urlSegment);

        $defaults = [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            '_controller' => \sprintf('%s::load', $serviceId),
            // Routes that use service IDs as controllers (not PHP class names) must set
            // _experimental explicitly as a route default at compile time to avoid ReflectionException
            // in ApiRoutesHaveASchemaTest::isExperimentalRoute().
            '_experimental' => false,
        ];

        if (!$hasPath) {
            $defaults['path'] = '';
        }

        $requirements = [];
        if ($hasPath) {
            $requirements['path'] = '.+';
        }

        return new ContentRouteDefinition(
            path: $path,
            name: $routeName,
            requirements: $requirements,
            defaults: $defaults,
        );
    }

    private function buildRouteName(string $sectionName, string $formatName, string $pathSegment): string
    {
        $segment = $sectionName === ContentSection::MAIN->value
            ? 'content'
            : $pathSegment;

        if ($sectionName === ContentSection::MAIN->value && $formatName === self::FORMAT_FULL) {
            return 'channel-api.content.detail';
        }

        if ($formatName === self::FORMAT_FULL) {
            return \sprintf('channel-api.%s', $segment);
        }

        return \sprintf('channel-api.%s.%s', $segment, $formatName);
    }
}
