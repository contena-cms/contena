<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\Kernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('contena.store.mock_handler', MockHandler::class)
        ->public();

    $services->set('contena.store_client', Client::class)
        ->public()
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service('contena.store.mock_handler'),
                    ]),
            ],
        ]);

    $services->set('contena.frw.mock_handler', MockHandler::class)
        ->public();

    $services->set('contena.frw_client', Client::class)
        ->public()
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service('contena.frw.mock_handler'),
                    ]),
            ],
        ]);

    $services->set('contena.store_download_client', Client::class)
        ->args([
            [
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service('contena.store.mock_handler'),
                    ]),
            ],
        ]);

    $services->set(InstanceService::class)
        ->args([
            Kernel::CONTENA_FALLBACK_VERSION,
            'this-is-a-unique-id',
        ]);
};
