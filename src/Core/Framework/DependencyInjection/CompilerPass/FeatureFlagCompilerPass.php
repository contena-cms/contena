<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Feature;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[BecomesInternal(version: 'v6.8.0')]
class FeatureFlagCompilerPass implements CompilerPassInterface
{
    public const ALIASES_TO_REMOVE = [
        'v6.8.0.0' => [
            'Contena\Administration\Controller\NotificationController',
            'Contena\Administration\Notification\NotificationDefinition',
            'Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface',
            'Contena\Core\Framework\Plugin\Util\AssetService',
            'Contena\Elasticsearch\Blog\SearchConfigLoader',
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        $featureFlags = $container->getParameter('contena.feature.flags');
        if (!\is_array($featureFlags)) {
            throw DependencyInjectionException::parameterHasWrongType('contena.feature.flags', 'array', get_debug_type($featureFlags));
        }

        Feature::registerFeatures($featureFlags);

        foreach ($container->findTaggedServiceIds('contena.feature') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['flag'])) {
                    throw DependencyInjectionException::featureTagMissingFlag($serviceId, 'contena.feature');
                }

                if (Feature::isActive($tag['flag'])) {
                    continue;
                }

                $container->removeDefinition($serviceId);

                break;
            }
        }

        foreach ($container->findTaggedServiceIds('contena.inactiveFeature') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['flag'])) {
                    throw DependencyInjectionException::featureTagMissingFlag($serviceId, 'contena.inactiveFeature');
                }

                if (!Feature::has($tag['flag']) || !Feature::isActive($tag['flag'])) {
                    continue;
                }

                $container->removeDefinition($serviceId);

                break;
            }
        }

        foreach (self::ALIASES_TO_REMOVE as $flag => $aliases) {
            if (!Feature::has($flag) || !Feature::isActive($flag)) {
                continue;
            }

            foreach ($aliases as $aliasId) {
                $container->removeAlias($aliasId);
            }
        }
    }
}
