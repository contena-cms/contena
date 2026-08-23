<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\System\Consent\Api\ConsentController;
use Contena\Core\System\Consent\ConsentDefinitionRegistry;
use Contena\Core\System\Consent\ConsentRepository;
use Contena\Core\System\Consent\ConsentScope;
use Contena\Core\System\Consent\Definition;
use Contena\Core\System\Consent\Log\ConsentChangedSubscriber;
use Contena\Core\System\Consent\Log\ConsentLogInterface;
use Contena\Core\System\Consent\Log\DatabaseLog;
use Contena\Core\System\Consent\Service\ConsentService;
use Contena\Core\System\Consent\Subscriber\SetupStagingEventSubscriber;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConsentController::class)
        ->public()
        ->args([
            new Reference(ConsentService::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(ConsentRepository::class)
        ->args([
            new Reference(Connection::class),
            new Reference(ClockInterface::class),
        ]);

    $services->set(ConsentDefinitionRegistry::class)
        ->args([
            new TaggedIteratorArgument('contena.consent.definition'),
        ]);

    $services->set(ConsentService::class)
        ->args([
            new TaggedIteratorArgument('contena.consent.scope'),
            new Reference(ConsentDefinitionRegistry::class),
            new Reference(ConsentRepository::class),
            new Reference('event_dispatcher'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ConsentScope\System::class)
        ->tag('contena.consent.scope');

    $services->set(ConsentScope\AdminUser::class)
        ->tag('contena.consent.scope');

    $services->set(ConsentScope\FrontendVisitor::class)
        ->tag('contena.consent.scope');

    $services->set(Definition\BackendData::class)
        ->tag('contena.consent.definition');

    $services->set(Definition\CookieConsent::class)
        ->tag('contena.consent.definition');

    $services->set(ConsentLogInterface::class)
        ->class(DatabaseLog::class)
        ->args([
            new Reference(Connection::class),
            new Reference(ClockInterface::class),
        ]);

    $services->set(ConsentChangedSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference(ConsentLogInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set(SetupStagingEventSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference(Connection::class),
        ]);
};
