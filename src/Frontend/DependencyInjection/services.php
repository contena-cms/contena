<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use Contena\Storefront\Framework\Routing\ContentSystemPreviewToolbarSubscriber;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Storefront\Framework\Cookie\CookieProvider;
use Contena\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('contena.twig.app_variable.allowed_server_params', [
            'server_name',
            'request_uri',
            'app_url',
            'http_user_agent',
            'http_host',
            'server_name',
            'server_port',
            'redirect_url',
            'https',
            'forwarded',
            'host',
            'remote_addr',
            'http_x_forwarded_for',
            'http_x_forwarded_host',
            'http_x_forwarded_proto',
            'http_x_forwarded_port',
            'http_x_forwarded_prefix',
        ]);

    $services = $containerConfigurator->services();












































































    // @deprecated tag:v6.8.0 Will be removed
    $services->set(CookieProviderInterface::class, CookieProvider::class)
        ->deprecate('contena/storefront', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0. Use the CookieGroupCollectEvent instead to introduce cookies.')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);
























    $services->set(ContentSystemPreviewToolbarSubscriber::class)
        ->tag('kernel.event_subscriber');
};
