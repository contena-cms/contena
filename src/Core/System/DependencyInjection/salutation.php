<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Salutation\AbstractSalutationsSorter;
use Contena\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;
use Contena\Core\System\Salutation\Api\SalutationKeyFkResolver;
use Contena\Core\System\Salutation\Channel\ChannelSalutationDefinition;
use Contena\Core\System\Salutation\Channel\SalutationRoute;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\Salutation\SalutationSorter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SalutationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelSalutationDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(SalutationTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalutationRoute::class)
        ->public()
        ->args([
            service('channel.salutation.repository'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(AbstractSalutationsSorter::class, SalutationSorter::class);

    $services->set(SalutationKeyFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.sync.fk_resolver');
};
