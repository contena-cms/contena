<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\System\TaxProvider\Aggregate\TaxProviderTranslation\TaxProviderTranslationDefinition;
use Contena\Core\System\TaxProvider\TaxProviderDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TaxProviderDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(TaxProviderTranslationDefinition::class)
        ->tag('contena.entity.definition');
};
