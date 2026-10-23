<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\System\Language\LanguageLoader;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Contena\Core\System\Locale\Api\LocaleCodeFkResolver;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\Locale\Subscriber\LocaleValidator;
use Contena\Core\System\Locale\SystemCheck\LocalesReadinessCheck;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LocaleDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LocaleTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LanguageLocaleCodeProvider::class)
        ->args([
            service(LanguageLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(LocaleValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(LocalesReadinessCheck::class)
        ->args([
            service('locale.repository'),
        ])
        ->tag('contena.system_check');

    $services->set(LocaleCodeFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.sync.fk_resolver');
};
