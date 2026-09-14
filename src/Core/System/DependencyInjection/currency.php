<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Contena\Core\System\Currency\CurrencyDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CurrencyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CurrencyTranslationDefinition::class)
        ->tag('contena.entity.definition');
};
