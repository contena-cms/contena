<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\Rule\Api\RuleConfigController;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Contena\Core\Framework\Rule\Container\NotRule;
use Contena\Core\Framework\Rule\Container\OrRule;
use Contena\Core\Framework\Rule\Container\XorRule;
use Contena\Core\Framework\Rule\DateRangeRule;
use Contena\Core\Framework\Rule\RuleIdMatcher;
use Contena\Core\Framework\Rule\ChannelRule;
use Contena\Core\Framework\Rule\ScriptRule;
use Contena\Core\Framework\Rule\SimpleRule;
use Contena\Core\Framework\Rule\TimeRangeRule;
use Contena\Core\Framework\Rule\WeekdayRule;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleConditionRegistry::class)
        ->args([
            tagged_iterator('contena.rule.definition'),
        ]);

    $services->set(RuleIdMatcher::class);

    $services->set(AndRule::class)
        ->tag('contena.rule.definition');

    $services->set(NotRule::class)
        ->tag('contena.rule.definition');

    $services->set(OrRule::class)
        ->tag('contena.rule.definition');

    $services->set(XorRule::class)
        ->tag('contena.rule.definition');

    $services->set(MatchAllLineItemsRule::class)
        ->tag('contena.rule.definition');

    $services->set(ScriptRule::class)
        ->tag('contena.rule.definition');

    $services->set(DateRangeRule::class)
        ->tag('contena.rule.definition');

    $services->set(SimpleRule::class)
        ->tag('contena.rule.definition');

    $services->set(ChannelRule::class)
        ->tag('contena.rule.definition');

    $services->set(TimeRangeRule::class)
        ->tag('contena.rule.definition');

    $services->set(WeekdayRule::class)
        ->tag('contena.rule.definition');

    $services->set(RuleConfigController::class)
        ->public()
        ->args([
            tagged_iterator('contena.rule.definition'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
