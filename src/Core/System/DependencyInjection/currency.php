<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Contena\Core\System\Currency\Api\CurrencyIsoCodeFkResolver;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Currency\CurrencyExceptionHandler;
use Contena\Core\System\Currency\CurrencyFormatter;
use Contena\Core\System\Currency\CurrencyLoadSubscriber;
use Contena\Core\System\Currency\CurrencyValidator;
use Contena\Core\System\Currency\Rule\CurrencyRule;
use Contena\Core\System\Currency\Channel\CurrencyRoute;
use Contena\Core\System\Currency\Channel\ChannelCurrencyDefinition;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CurrencyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CurrencyCountryRoundingDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelCurrencyDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(CurrencyTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CurrencyLoadSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CurrencyValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(CurrencyExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(CurrencyRule::class)
        ->tag('contena.rule.definition');

    $services->set(CurrencyFormatter::class)
        ->public()
        ->args([
            service(LanguageLocaleCodeProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CurrencyRoute::class)
        ->public()
        ->args([
            service('channel.currency.repository'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CurrencyIsoCodeFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.sync.fk_resolver');
};
