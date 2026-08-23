<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Contena\Core\Content\Rule\CachedRuleLoader;
use Contena\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Contena\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Contena\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber;
use Contena\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Content\Rule\RuleLoader;
use Contena\Core\Content\Rule\RuleValidator;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Rule\ChannelRule;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\NotRule;
use Contena\Core\Framework\Rule\Container\OrRule;
use Contena\Core\Framework\Rule\Container\XorRule;
use Contena\Core\Framework\Rule\DateRangeRule;
use Contena\Core\Framework\Rule\SimpleRule;
use Contena\Core\Framework\Rule\TimeRangeRule;
use Contena\Core\Framework\Rule\WeekdayRule;
use Contena\Core\System\Language\Rule\LanguageRule;
use Contena\Core\System\User\Rule\DaysSinceFirstLoginRule;
use Contena\Core\System\User\Rule\DaysSinceLastLoginRule;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleDefinition::class)->tag('contena.entity.definition');
    $services->set(RuleConditionDefinition::class)->tag('contena.entity.definition');
    $services->set(RuleTagDefinition::class)->tag('contena.entity.definition');

    foreach ([AndRule::class, OrRule::class, NotRule::class, XorRule::class, DateRangeRule::class, TimeRangeRule::class, WeekdayRule::class, SimpleRule::class, ChannelRule::class, LanguageRule::class, DaysSinceFirstLoginRule::class, DaysSinceLastLoginRule::class] as $rule) {
        $services->set($rule)->tag('contena.rule.condition');
    }

    $services->set(RuleConditionRegistry::class)->args([tagged_iterator('contena.rule.condition')]);
    $services->set(RuleValidator::class)->args([
        service('validator'),
        service(RuleConditionRegistry::class),
        service('rule_condition.repository'),
    ])->tag('kernel.event_subscriber');
    $services->set(RulePayloadUpdater::class)->args([service(Connection::class), service(RuleConditionRegistry::class), service(ClockInterface::class)]);
    $services->set(RulePayloadSubscriber::class)->args([service(RulePayloadUpdater::class)])->tag('kernel.event_subscriber');
    $services->set(RuleAreaUpdater::class)->args([
        service(Connection::class),
        service(RuleDefinition::class),
        service(RuleConditionRegistry::class),
        service(CacheInvalidator::class),
        service(DefinitionInstanceRegistry::class),
        service(ClockInterface::class),
    ])->tag('kernel.event_subscriber');
    $services->set(RuleIndexer::class)->args([
        service(IteratorFactory::class),
        service('rule.repository'),
        service(RulePayloadUpdater::class),
        service(RuleAreaUpdater::class),
        service('event_dispatcher'),
    ])->tag('contena.entity_indexer');

    $services->set(RuleLoader::class)->args([service('rule.repository')]);
    $services->set(CachedRuleLoader::class)->decorate(RuleLoader::class, null, -1000)->args([
        service(CachedRuleLoader::class . '.inner'),
        service('cache.object'),
    ])->tag('kernel.event_subscriber');
    $services->alias(AbstractRuleLoader::class, RuleLoader::class);
};
