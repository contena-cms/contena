<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Contena\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationDefinition;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;
use Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationDefinition;
use Contena\Core\System\Organization\DataAbstractionLayer\OrganizationIndexer;
use Contena\Core\System\Organization\OrganizationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(OrganizationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(OrganizationTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(OrganizationUnitDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(OrganizationUnitTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(OrganizationIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('organization.repository'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
        ])
        ->tag('contena.entity_indexer');
};
