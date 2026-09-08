<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\System\CustomEntity\Api\CustomEntityApiController;
use Contena\Core\System\CustomEntity\CustomEntityDefinition;
use Contena\Core\System\CustomEntity\CustomEntityRegistrar;
use Contena\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Contena\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Contena\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Contena\Core\System\CustomEntity\Schema\SchemaUpdater;
use Contena\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Contena\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Lock\LockFactory;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CustomEntityRegistrar::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(CustomEntityPersister::class)
        ->args([
            service(Connection::class),
            service('cache.object'),
            service(ClockInterface::class),
        ]);

    $services->set(CustomEntityDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CustomEntityNameValidator::class);

    $services->set(SchemaUpdater::class)
        ->args([
            service(CustomEntityNameValidator::class),
        ]);

    $services->set(CustomEntitySchemaUpdater::class)
        ->public()
        ->args([
            service(Connection::class),
            service(LockFactory::class),
            service(SchemaUpdater::class),
        ]);

    $services->set(CustomEntityApiController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('serializer'),
            service(RequestCriteriaBuilder::class),
            service(EntityProtectionValidator::class),
            service(AclCriteriaValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(CustomEntityXmlSchemaValidator::class)
        ->args([
            service(CustomEntityNameValidator::class),
        ]);
    $services->set(AdminUiXmlSchemaValidator::class);
};
