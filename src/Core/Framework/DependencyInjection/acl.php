<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Api\Acl\AclAnnotationValidator;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Acl\AclWriteValidator;
use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Contena\Core\Framework\Api\Controller\AclController;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AclRoleDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AclUserRoleDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AclWriteValidator::class)
        ->args([
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AclAnnotationValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(AclCriteriaValidator::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AclController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('event_dispatcher'),
            service('router'),
        ])
        ->call('setContainer', [service('service_container')]);
};
