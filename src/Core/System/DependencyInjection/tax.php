<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Contena\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;
use Contena\Core\System\Tax\Api\TaxRateFkResolver;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Contena\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Contena\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Contena\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(TaxDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(TaxRuleDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(TaxRuleTypeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(TaxRuleTypeTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(EntireCountryRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(IndividualStatesRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(ZipCodeRangeRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(ZipCodeRuleTypeFilter::class)
        ->tag('tax.rule_type_filter');

    $services->set(TaxRateFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.sync.fk_resolver');
};
