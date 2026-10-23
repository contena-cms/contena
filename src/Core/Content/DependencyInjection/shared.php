<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Shared\MailFlow\DataProvider\UserGroupProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ChannelProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\StateMachineStateProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\AdminUserRecoveryProvider;
use Contena\Core\Content\Shared\MailFlow\DocumentResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DocumentResolver::class)
        ->args([
            service('document.repository'),
        ]);

    // Mail & Flow Data Providers
        $services->set(UserProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'user']);

    $services->set(UserGroupProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'user_group']);

    $services->set(BlogProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'blog']);

    $services->set(UserRecoveryProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'user_recovery']);

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

    $services->set(AdminUserRecoveryProvider::class)
        ->args([
            service('event_dispatcher'),
            service('service_container'),
        ])
        ->tag('contena.mail.data_provider', ['key' => 'admin_user_recovery']);
};
