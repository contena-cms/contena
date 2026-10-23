<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroupRegistrationChannel\UserGroupRegistrationChannelDefinition;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserTag\UserTagDefinition;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistDefinition;
use Contena\Core\System\User\Aggregate\UserWishlistBlog\UserWishlistBlogDefinition;
use Contena\Core\System\User\Api\ConvertGuestController;
use Contena\Core\System\User\Api\UserGroupRegistrationActionController;
use Contena\Core\System\User\CleanupUserRecoveryTask;
use Contena\Core\System\User\CleanupUserRecoveryTaskHandler;
use Contena\Core\System\User\Command\DeleteUnusedGuestUserCommand;
use Contena\Core\System\User\Cookie\WishlistCookieCollectListener;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserValueResolver;
use Contena\Core\System\User\DataAbstractionLayer\UserIndexer;
use Contena\Core\System\User\DataAbstractionLayer\UserWishlistBlogExceptionHandler;
use Contena\Core\System\User\DeleteUnusedGuestUserHandler;
use Contena\Core\System\User\DeleteUnusedGuestUserService;
use Contena\Core\System\User\DeleteUnusedGuestUserTask;
use Contena\Core\System\User\ImitateUserTokenGenerator;
use Contena\Core\System\User\Password\LegacyEncoder\Md5;
use Contena\Core\System\User\Password\LegacyEncoder\Sha256;
use Contena\Core\System\User\Password\LegacyPasswordVerifier;
use Contena\Core\System\User\Channel\AccountNewsletterRecipientRoute;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\AddWishlistBlogRoute;
use Contena\Core\System\User\Channel\ChangeUserProfileRoute;
use Contena\Core\System\User\Channel\ChangeEmailRoute;
use Contena\Core\System\User\Channel\ChangeLanguageRoute;
use Contena\Core\System\User\Channel\ChangePasswordRoute;
use Contena\Core\System\User\Channel\ConvertGuestRoute;
use Contena\Core\System\User\Channel\UserGroupRegistrationSettingsRoute;
use Contena\Core\System\User\Channel\UserRecoveryIsExpiredRoute;
use Contena\Core\System\User\Channel\UserRoute;
use Contena\Core\System\User\Channel\DeleteAddressRoute;
use Contena\Core\System\User\Channel\DeleteUserRoute;
use Contena\Core\System\User\Channel\ImitateUserRoute;
use Contena\Core\System\User\Channel\ListAddressRoute;
use Contena\Core\System\User\Channel\LoadWishlistRoute;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\System\User\Channel\LogoutRoute;
use Contena\Core\System\User\Channel\MergeWishlistBlogRoute;
use Contena\Core\System\User\Channel\RegisterConfirmRoute;
use Contena\Core\System\User\Channel\RegisterRoute;
use Contena\Core\System\User\Channel\RemoveWishlistBlogRoute;
use Contena\Core\System\User\Channel\ResetPasswordRoute;
use Contena\Core\System\User\Channel\ChannelUserAddressDefinition;
use Contena\Core\System\User\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Channel\SwitchDefaultAddressRoute;
use Contena\Core\System\User\Channel\UpsertAddressRoute;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\System\User\Service\GuestAuthenticator;
use Contena\Core\System\User\Subscriber\AddressHashSubscriber;
use Contena\Core\System\User\Subscriber\UserAddressSubscriber;
use Contena\Core\System\User\Subscriber\UserBeforeDeleteSubscriber;
use Contena\Core\System\User\Subscriber\UserChangePasswordSubscriber;
use Contena\Core\System\User\Subscriber\UserEmailUniqueSubscriber;
use Contena\Core\System\User\Subscriber\UserFlowEventsSubscriber;
use Contena\Core\System\User\Subscriber\UserGroupRegistrationSeoUrlSubscriber;
use Contena\Core\System\User\Subscriber\UserLanguageChannelSubscriber;
use Contena\Core\System\User\Subscriber\UserLogoutSubscriber;
use Contena\Core\System\User\Subscriber\UserMetaFieldSubscriber;
use Contena\Core\System\User\Subscriber\UserRemoteAddressSubscriber;
use Contena\Core\System\User\Subscriber\UserTokenSubscriber;
use Contena\Core\System\User\Validation\AddressValidationFactory;
use Contena\Core\System\User\Validation\Constraint\UserEmailUniqueValidator;
use Contena\Core\System\User\Validation\Constraint\UserPasswordMatchesValidator;
use Contena\Core\System\User\Validation\Constraint\UserVatIdentificationValidator;
use Contena\Core\System\User\Validation\Constraint\UserZipCodeValidator;
use Contena\Core\System\User\Validation\UserEmailUniqueChecker;
use Contena\Core\System\User\Validation\UserProfileValidationFactory;
use Contena\Core\System\User\Validation\UserValidationFactory;
use Contena\Core\System\User\Validation\PasswordValidationFactory;
use Contena\Core\Content\Media\File\DownloadResponseGenerator;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\StoreApiCustomFieldMapper;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('user.account_types', [
            UserEntity::ACCOUNT_TYPE_BUSINESS,
            UserEntity::ACCOUNT_TYPE_PRIVATE,
        ]);

    $services = $containerConfigurator->services();

    $services->set(UserDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(UserGroupTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserAddressDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(UserRecoveryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserGroupDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserGroupRegistrationChannelDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AccountService::class)
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(LegacyPasswordVerifier::class),
            service(SwitchDefaultAddressRoute::class),
            service(DoubleOptInService::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DoubleOptInService::class)
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service('channel_domain.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(AddressValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(UserProfileValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
            param('user.account_types'),
        ]);

    $services->set(PasswordValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(UserValidationFactory::class)
        ->args([
            service(UserProfileValidationFactory::class),
        ]);

    $services->set(UserEmailUniqueChecker::class)
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ]);

    $services->set(UserEmailUniqueValidator::class)
        ->args([
            service(UserEmailUniqueChecker::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(UserPasswordMatchesValidator::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(UserVatIdentificationValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(UserZipCodeValidator::class)
        ->args([
            service('country.repository'),
        ])
        ->tag('validator.constraint_validator');

    $services->set(Md5::class)
        ->tag('contena.legacy_encoder');

    $services->set(Sha256::class)
        ->tag('contena.legacy_encoder');

    $services->set(LegacyPasswordVerifier::class)
        ->args([
            tagged_iterator('contena.legacy_encoder'),
        ]);

    $services->set(AddressHashSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(UserMetaFieldSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(GuestAuthenticator::class);

    $services->set(UserRemoteAddressSubscriber::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserGroupRegistrationSeoUrlSubscriber::class)
        ->args([
            service('user_group.repository'),
            service('seo_url.repository'),
            service('language.repository'),
            service(SeoUrlPersister::class),
            service('slugify'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserTokenSubscriber::class)
        ->args([
            service(ChannelContextPersister::class),
            service('request_stack'),
            service(SessionContextTokenAccessor::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserChangePasswordSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserFlowEventsSubscriber::class)
        ->args([
            service(EventDispatcherInterface::class),
            service(ChannelContextRestorer::class),
            service(UserIndexer::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserLogoutSubscriber::class)
        ->args([
            service(RequestStack::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LoginRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(RequestStack::class),
            service(RateLimiter::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LogoutRoute::class)
        ->public()
        ->args([
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(ChannelContextService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SendPasswordRecoveryMailRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('user_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service(RequestStack::class),
            service('contena.rate_limiter'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ResetPasswordRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('user_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(RequestStack::class),
            service('contena.rate_limiter'),
            service(PasswordValidationFactory::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(UserRecoveryIsExpiredRoute::class)
        ->public()
        ->args([
            service('user_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangeUserProfileRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(UserProfileValidationFactory::class),
            service(StoreApiCustomFieldMapper::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangePasswordRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(DataValidator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangeEmailRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service('user_recovery.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangeLanguageRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ConvertGuestRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(PasswordValidationFactory::class),
            service(RequestStack::class),
            service('contena.rate_limiter'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(UserRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DeleteUserRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RegisterRoute::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(DataValidator::class),
            service(UserValidationFactory::class),
            service(AddressValidationFactory::class),
            service(SystemConfigService::class),
            service('user.repository'),
            service(ChannelContextPersister::class),
            service('channel.country.repository'),
            service(Connection::class),
            service(ChannelContextService::class),
            service(StoreApiCustomFieldMapper::class),
            service(PasswordValidationFactory::class),
            service(DoubleOptInService::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RegisterConfirmRoute::class)
        ->public()
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ChannelContextPersister::class),
            service(ChannelContextService::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ListAddressRoute::class)
        ->public()
        ->args([
            service('channel.user_address.repository'),
            service('event_dispatcher'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(UpsertAddressRoute::class)
        ->public()
        ->args([
            service('user_address.repository'),
            service('channel.user_address.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(AddressValidationFactory::class),
            service(SystemConfigService::class),
            service(StoreApiCustomFieldMapper::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DeleteAddressRoute::class)
        ->public()
        ->args([
            service('user_address.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SwitchDefaultAddressRoute::class)
        ->public()
        ->args([
            service('user_address.repository'),
            service('user.repository'),
            service(EventDispatcherInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(UserGroupRegistrationSettingsRoute::class)
        ->public()
        ->args([
            service('user_group.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChannelUserAddressDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(UserIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('user.repository'),
            service(ManyToManyIdFieldUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.entity_indexer', ['priority' => 100]);

    $services->set(ConvertGuestController::class)
        ->public()
        ->args([
            service('user.repository'),
            service(ChannelContextService::class),
            service(ConvertGuestRoute::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(Connection::class),
        ]);

    $services->set(UserGroupRegistrationActionController::class)
        ->public()
        ->args([
            service('user.repository'),
            service('user_group.repository'),
            service('event_dispatcher'),
            service(ChannelContextRestorer::class),
        ]);

    $services->set(UserWishlistDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserWishlistBlogDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LoadWishlistRoute::class)
        ->public()
        ->args([
            service('user_wishlist.repository'),
            service('channel.blog.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(BlogCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(AddWishlistBlogRoute::class)
        ->public()
        ->args([
            service('user_wishlist.repository'),
            service('channel.blog.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RemoveWishlistBlogRoute::class)
        ->public()
        ->args([
            service('user_wishlist.repository'),
            service('user_wishlist_blog.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(UserWishlistBlogExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(MergeWishlistBlogRoute::class)
        ->public()
        ->args([
            service('user_wishlist.repository'),
            service('channel.blog.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WishlistCookieCollectListener::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(UserValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1002]);

    $services->set(AccountNewsletterRecipientRoute::class)
        ->public()
        ->args([
                        service(ExtensionDispatcher::class),
        ]);

    $services->set(ImitateUserRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(ImitateUserTokenGenerator::class),
            service(LogoutRoute::class),
            service(ChannelContextFactory::class),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DeleteUnusedGuestUserService::class)
        ->args([
            service('user.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(ImitateUserTokenGenerator::class)
        ->args([
            env('APP_SECRET'),
            service('contena.jwt_config'),
            service(DataValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteUnusedGuestUserCommand::class)
        ->args([
            service(DeleteUnusedGuestUserService::class),
        ])
        ->tag('console.command');

    $services->set(DeleteUnusedGuestUserTask::class)
        ->tag('contena.scheduled.task');

    $services->set(DeleteUnusedGuestUserHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(DeleteUnusedGuestUserService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupUserRecoveryTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupUserRecoveryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(UserBeforeDeleteSubscriber::class)
        ->args([
            service('user.repository'),
            service('channel.repository'),
            service(ChannelContextService::class),
            service('event_dispatcher'),
            service(JsonEntityEncoder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserLanguageChannelSubscriber::class)
        ->args([
            service('channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserEmailUniqueSubscriber::class)
        ->args([
            service(Connection::class),
            service(UserEmailUniqueChecker::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserAddressSubscriber::class)
        ->tag('kernel.event_subscriber');
};
