<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelRuleLoader;
use Contena\Core\System\Channel\Context\MemberContextRestorer;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroupRegistrationChannel\MemberGroupRegistrationChannelDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroupTranslation\MemberGroupTranslationDefinition;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryDefinition;
use Contena\Core\System\Member\Aggregate\MemberTag\MemberTagDefinition;
use Contena\Core\System\Member\Api\MemberGroupRegistrationActionController;
use Contena\Core\System\Member\Channel\AccountService;
use Contena\Core\System\Member\Channel\ChangeEmailRoute;
use Contena\Core\System\Member\Channel\ChangeLanguageRoute;
use Contena\Core\System\Member\Channel\ChangeMemberProfileRoute;
use Contena\Core\System\Member\Channel\ChangePasswordRoute;
use Contena\Core\System\Member\Channel\ChannelMemberAddressDefinition;
use Contena\Core\System\Member\Channel\DeleteAddressRoute;
use Contena\Core\System\Member\Channel\DeleteMemberRoute;
use Contena\Core\System\Member\Channel\ImitateMemberRoute;
use Contena\Core\System\Member\Channel\ListAddressRoute;
use Contena\Core\System\Member\Channel\LoginRoute;
use Contena\Core\System\Member\Channel\LogoutRoute;
use Contena\Core\System\Member\Channel\MemberGroupRegistrationSettingsRoute;
use Contena\Core\System\Member\Channel\MemberRecoveryIsExpiredRoute;
use Contena\Core\System\Member\Channel\MemberRoute;
use Contena\Core\System\Member\Channel\RegisterConfirmRoute;
use Contena\Core\System\Member\Channel\RegisterRoute;
use Contena\Core\System\Member\Channel\ResetPasswordRoute;
use Contena\Core\System\Member\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\Channel\UpsertAddressRoute;
use Contena\Core\System\Member\CleanupMemberRecoveryTask;
use Contena\Core\System\Member\CleanupMemberRecoveryTaskHandler;
use Contena\Core\System\Member\DataAbstractionLayer\MemberIndexer;
use Contena\Core\System\Member\ImitateMemberTokenGenerator;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberValueResolver;
use Contena\Core\System\Member\Rule\DaysSinceFirstLoginRule;
use Contena\Core\System\Member\Rule\DaysSinceLastLoginRule;
use Contena\Core\System\Member\Rule\EmailRule;
use Contena\Core\System\Member\Rule\IsActiveRule;
use Contena\Core\System\Member\Rule\MemberAgeRule;
use Contena\Core\System\Member\Rule\MemberBirthdayRule;
use Contena\Core\System\Member\Rule\MemberCreatedByAdminRule;
use Contena\Core\System\Member\Rule\MemberCustomFieldRule;
use Contena\Core\System\Member\Rule\MemberGroupRule;
use Contena\Core\System\Member\Rule\MemberLoggedInRule;
use Contena\Core\System\Member\Rule\MemberNumberRule;
use Contena\Core\System\Member\Rule\MemberRequestedGroupRule;
use Contena\Core\System\Member\Rule\MemberTagRule;
use Contena\Core\System\Member\Rule\NameRule;
use Contena\Core\System\Member\Service\DoubleOptInService;
use Contena\Core\System\Member\Subscriber\AddressHashSubscriber;
use Contena\Core\System\Member\Subscriber\MemberBeforeDeleteSubscriber;
use Contena\Core\System\Member\Subscriber\MemberEmailUniqueSubscriber;
use Contena\Core\System\Member\Subscriber\MemberFlowEventsSubscriber;
use Contena\Core\System\Member\Subscriber\MemberLanguageChannelSubscriber;
use Contena\Core\System\Member\Subscriber\MemberLogoutSubscriber;
use Contena\Core\System\Member\Subscriber\MemberRemoteAddressSubscriber;
use Contena\Core\System\Member\Subscriber\MemberTokenSubscriber;
use Contena\Core\System\Member\Validation\AddressValidationFactory;
use Contena\Core\System\Member\Validation\Constraint\MemberEmailUniqueValidator;
use Contena\Core\System\Member\Validation\Constraint\MemberPasswordMatchesValidator;
use Contena\Core\System\Member\Validation\MemberEmailUniqueChecker;
use Contena\Core\System\Member\Validation\MemberProfileValidationFactory;
use Contena\Core\System\Member\Validation\MemberValidationFactory;
use Contena\Core\System\Member\Validation\PasswordValidationFactory;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MemberDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(MemberGroupTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MemberAddressDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(MemberRecoveryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MemberGroupDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MemberGroupRegistrationChannelDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MemberTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AccountService::class)
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(MemberContextRestorer::class),
            service(DoubleOptInService::class),
            service(ClockInterface::class),
        ]);

    $services->set(MemberContextRestorer::class)
        ->args([
            service(AbstractChannelContextFactory::class),
            service(ChannelContextPersister::class),
            service(ChannelRuleLoader::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(DoubleOptInService::class)
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service('channel_domain.repository'),
            service(ClockInterface::class),
        ]);

    foreach ([
        MemberGroupRule::class,
        MemberRequestedGroupRule::class,
        MemberTagRule::class,
        MemberNumberRule::class,
        EmailRule::class,
        IsActiveRule::class,
        NameRule::class,
        MemberLoggedInRule::class,
        MemberCustomFieldRule::class,
        MemberBirthdayRule::class,
        MemberCreatedByAdminRule::class,
        MemberAgeRule::class,
        DaysSinceLastLoginRule::class,
        DaysSinceFirstLoginRule::class,
    ] as $rule) {
        $services->set($rule)->tag('contena.rule.condition');
    }

    $services->set(AddressValidationFactory::class)
        ->args([service(SystemConfigService::class)]);

    $services->set(MemberProfileValidationFactory::class)
        ->args([service(SystemConfigService::class)]);

    $services->set(PasswordValidationFactory::class)
        ->args([service(SystemConfigService::class)]);

    $services->set(MemberValidationFactory::class)
        ->args([service(MemberProfileValidationFactory::class)]);

    $services->set(MemberEmailUniqueChecker::class)
        ->args([service(Connection::class)]);

    $services->set(MemberEmailUniqueValidator::class)
        ->args([service(MemberEmailUniqueChecker::class)])
        ->tag('validator.constraint_validator');

    $services->set(MemberPasswordMatchesValidator::class)
        ->args([service(AccountService::class)])
        ->tag('validator.constraint_validator');

    $services->set(AddressHashSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(MemberRemoteAddressSubscriber::class)
        ->args([
            service(Connection::class),
            service(RequestStack::class),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MemberTokenSubscriber::class)
        ->args([
            service(ChannelContextPersister::class),
            service(RequestStack::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelContextRestorer::class)
        ->args([
            service(AbstractChannelContextFactory::class),
            service(Connection::class),
        ]);

    $services->set(MemberFlowEventsSubscriber::class)
        ->args([
            service(EventDispatcherInterface::class),
            service(ChannelContextRestorer::class),
            service(MemberIndexer::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MemberLogoutSubscriber::class)
        ->args([service(RequestStack::class)])
        ->tag('kernel.event_subscriber');

    $services->set(SendPasswordRecoveryMailRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('member_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service(RequestStack::class),
            service('contena.rate_limiter'),
        ]);

    $services->set(ResetPasswordRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('member_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(RequestStack::class),
            service('contena.rate_limiter'),
            service(PasswordValidationFactory::class),
            service(ClockInterface::class),
        ]);

    $services->set(MemberRecoveryIsExpiredRoute::class)
        ->public()
        ->args([
            service('member_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(LoginRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(RequestStack::class),
            service('contena.rate_limiter'),
        ]);

    $services->set(LogoutRoute::class)
        ->public()
        ->args([
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(ChannelContextServiceInterface::class),
        ]);

    $services->set(ChangeMemberProfileRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(MemberProfileValidationFactory::class),
            service(ChannelApiCustomFieldMapper::class),
        ]);

    $services->set(ChangePasswordRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(DataValidator::class),
        ]);

    $services->set(ChangeEmailRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service('member_recovery.repository'),
        ]);

    $services->set(ChangeLanguageRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
        ]);

    $services->set(MemberRoute::class)
        ->public()
        ->args([service('member.repository')]);

    $services->set(DeleteMemberRoute::class)
        ->public()
        ->args([service('member.repository')]);

    $services->set(RegisterRoute::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(AbstractNumberRangeValueGenerator::class),
            service(DataValidator::class),
            service(MemberValidationFactory::class),
            service(SystemConfigService::class),
            service('member.repository'),
            service(ChannelContextPersister::class),
            service(ChannelContextServiceInterface::class),
            service(ChannelApiCustomFieldMapper::class),
            service(PasswordValidationFactory::class),
            service(DoubleOptInService::class),
            service(ClockInterface::class),
        ]);

    $services->set(RegisterConfirmRoute::class)
        ->public()
        ->args([
            service('member.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ChannelContextPersister::class),
            service(ChannelContextServiceInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(ListAddressRoute::class)
        ->public()
        ->args([
            service('channel.member_address.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(UpsertAddressRoute::class)
        ->public()
        ->args([
            service('member_address.repository'),
            service('channel.member_address.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(AddressValidationFactory::class),
            service(ChannelApiCustomFieldMapper::class),
        ]);

    $services->set(DeleteAddressRoute::class)
        ->public()
        ->args([service('member_address.repository')]);

    $services->set(MemberGroupRegistrationSettingsRoute::class)
        ->public()
        ->args([service('member_group.repository')]);

    $services->set(ChannelMemberAddressDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(MemberIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('member.repository'),
            service(ManyToManyIdFieldUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.entity_indexer', ['priority' => 100]);

    $services->set(MemberGroupRegistrationActionController::class)
        ->public()
        ->args([
            service('member.repository'),
            service('member_group.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(MemberValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1002]);

    $services->set(ImitateMemberRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(ImitateMemberTokenGenerator::class),
            service(LogoutRoute::class),
            service(AbstractChannelContextFactory::class),
        ]);

    $services->set(ImitateMemberTokenGenerator::class)
        ->args([
            service('contena.jwt_config'),
            service(DataValidator::class),
        ]);

    $services->set(CleanupMemberRecoveryTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupMemberRecoveryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service(ClockInterface::class),
            service(TenantScopeContextProvider::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(MemberBeforeDeleteSubscriber::class)
        ->args([
            service('member.repository'),
            service('channel.repository'),
            service(ChannelContextServiceInterface::class),
            service('event_dispatcher'),
            service(JsonEntityEncoder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MemberLanguageChannelSubscriber::class)
        ->args([service('channel.repository')])
        ->tag('kernel.event_subscriber');

    $services->set(MemberEmailUniqueSubscriber::class)
        ->args([
            service(Connection::class),
            service(MemberEmailUniqueChecker::class),
        ])
        ->tag('kernel.event_subscriber');
};
