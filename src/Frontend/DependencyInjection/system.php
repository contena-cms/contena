<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\System\Channel\ChannelAnalyticsLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ChannelAnalyticsLoader::class)
        ->args([
            service('channel_analytics.repository'),
        ])
        ->tag('kernel.event_listener', ['event' => FrontendRenderEvent::class, 'method' => 'loadAnalytics', 'priority' => 2000]);
};
