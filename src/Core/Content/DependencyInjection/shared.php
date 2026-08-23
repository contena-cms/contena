<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ChannelProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberGroupProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberRecoveryProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\StateMachineStateProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Mail & Flow Data Providers
    $services->set(MemberProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'member']);

    $services->set(MemberGroupProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'member_group']);

    $services->set(BlogProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'blog']);

    $services->set(MemberRecoveryProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'member_recovery']);

    $services->set(ChannelProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'channel']);

    $services->set(StateMachineStateProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'state_machine_state']);

    $services->set(UserRecoveryProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'user_recovery']);
};
