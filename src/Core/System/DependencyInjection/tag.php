<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\System\Tag\Service\FilterTagIdsService;
use Contena\Core\System\Tag\TagDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(FilterTagIdsService::class)
        ->public()
        ->args([
            service(TagDefinition::class),
            service(Connection::class),
            service(CriteriaQueryBuilder::class),
        ]);
};
