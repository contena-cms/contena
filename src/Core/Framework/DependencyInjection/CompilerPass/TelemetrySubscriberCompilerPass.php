<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * When telemetry metrics are globally disabled, services tagged with `contena.telemetry.subscriber`
 * or `contena.telemetry.periodic_metric_collector` are removed to avoid overhead.
 */
class TelemetrySubscriberCompilerPass implements CompilerPassInterface
{
    private const array REMOVABLE_TAGS = [
        'contena.telemetry.subscriber',
        'contena.telemetry.periodic_metric_collector',
    ];

    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('contena.telemetry.metrics.enabled')) {
            return;
        }

        foreach (self::REMOVABLE_TAGS as $tag) {
            foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
                $container->removeDefinition($serviceId);
            }
        }
    }
}
