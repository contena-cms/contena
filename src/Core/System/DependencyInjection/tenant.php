<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\System\Tenant\Resolver\SubdomainTenantResolver;
use Contena\Core\System\Tenant\Resolver\TenantResolverChain;
use Contena\Core\System\Tenant\Subscriber\ResolvedTenantSubscriber;
use Contena\Core\System\Tenant\Subscriber\TenantCodeImmutableSubscriber;
use Contena\Core\System\Tenant\TenantEntity;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Attribute-based entity; autoconfiguration applies the `contena.entity`
    // tag which registers `tenant.definition` and `tenant.repository`.
    $services->set(TenantEntity::class)->autoconfigure();

    // The tenant resolver chain applies only to tenant-addressed requests;
    // the administration is addressed by the "subdomain equals tenant code"
    // convention. Further resolvers can be registered with the
    // `contena.tenant_resolver` tag.
    $services->set(SubdomainTenantResolver::class)
        ->args([service(Connection::class), service('validator')])
        ->tag('contena.tenant_resolver', ['priority' => 50]);

    $services->set(TenantResolverChain::class)
        ->args([tagged_iterator('contena.tenant_resolver')]);

    $services->set(ResolvedTenantSubscriber::class)
        ->args([service(TenantResolverChain::class)])
        ->tag('kernel.event_subscriber');

    $services->set(TenantCodeImmutableSubscriber::class)
        ->args([service(Connection::class)])
        ->tag('kernel.event_subscriber');

    $services->set(TenantScopeContextProvider::class)
        ->args([service(Connection::class)]);
};
