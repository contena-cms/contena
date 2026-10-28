<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();





    $services->set('contena.captcha.client', Client::class);



};
