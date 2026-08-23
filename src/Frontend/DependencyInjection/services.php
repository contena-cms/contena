<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Cookie\Channel\CookieConsentLogRoute;
use Contena\Core\Content\Cookie\Channel\CookieRoute;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\SeoResolver;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\Channel\SitemapFileRoute;
use Contena\Core\Content\Sitemap\Channel\SitemapRoute;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRoute;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\Event\BeforeSendResponseEvent;
use Contena\Core\Framework\Routing\MaintenanceModeResolver as CoreMaintenanceModeResolver;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Country\Channel\AbstractCountryRoute;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Member\Channel\ChangeEmailRoute;
use Contena\Core\System\Member\Channel\ChangeMemberProfileRoute;
use Contena\Core\System\Member\Channel\ChangePasswordRoute;
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
use Contena\Core\System\Region\Channel\AbstractRegionRoute;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\AccountProfileController;
use Contena\Frontend\Controller\AddressController;
use Contena\Frontend\Controller\Api\CaptchaController as ApiCaptchaController;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Controller\BlogController;
use Contena\Frontend\Controller\CaptchaController;
use Contena\Frontend\Controller\ContentSystemPreviewController;
use Contena\Frontend\Controller\ContextController;
use Contena\Frontend\Controller\CookieController;
use Contena\Frontend\Controller\ErrorController;
use Contena\Frontend\Controller\LandingPageController;
use Contena\Frontend\Controller\MaintenanceController;
use Contena\Frontend\Controller\NavigationController;
use Contena\Frontend\Controller\RegionController;
use Contena\Frontend\Controller\RegisterController;
use Contena\Frontend\Controller\RobotsController;
use Contena\Frontend\Controller\SearchController;
use Contena\Frontend\Controller\SitemapController;
use Contena\Frontend\Controller\StorybookController;
use Contena\Frontend\Controller\VerificationHashController;
use Contena\Frontend\Controller\WellKnownController;
use Contena\Frontend\Framework\Cache\CacheCookieEventSubscriber;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;
use Contena\Frontend\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Contena\Frontend\Framework\Command\ChannelCreateWebCommand;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Media\FrontendMediaUploader;
use Contena\Frontend\Framework\Media\FrontendMediaValidatorRegistry;
use Contena\Frontend\Framework\Media\Validator\FrontendMediaDocumentValidator;
use Contena\Frontend\Framework\Media\Validator\FrontendMediaImageValidator;
use Contena\Frontend\Framework\Routing\BlogListingPageOutOfRangeSubscriber;
use Contena\Frontend\Framework\Routing\CachedDomainLoader;
use Contena\Frontend\Framework\Routing\CachedDomainLoaderInvalidator;
use Contena\Frontend\Framework\Routing\CanonicalLinkListener;
use Contena\Frontend\Framework\Routing\ClearSiteDataListener;
use Contena\Frontend\Framework\Routing\DomainLoader;
use Contena\Frontend\Framework\Routing\DomainNotMappedListener;
use Contena\Frontend\Framework\Routing\FrontendRouteEventSubscriber;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\FrontendSubscriber;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Framework\Routing\NotFound\NotFoundSubscriber;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\ResponseHeaderListener;
use Contena\Frontend\Framework\Routing\RobotsRouteScopeWhitelist;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Frontend\Framework\Routing\StorybookRouteScopeAllowList;
use Contena\Frontend\Framework\Routing\TemplateDataSubscriber;
use Contena\Frontend\Framework\Routing\TenantDefaultDomainLoader;
use Contena\Frontend\Framework\Store\Subscriber\ExtensionThemeDetectionSubscriber;
use Contena\Frontend\Framework\SystemCheck\BlogDetailReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\BlogListingReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\ChannelsReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Contena\Frontend\Framework\Twig\Components\TwigComponentRenderEventListener;
use Contena\Frontend\Framework\Twig\ErrorTemplateResolver;
use Contena\Frontend\Framework\Twig\Extension\IconCacheTwigFilter;
use Contena\Frontend\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Contena\Frontend\Framework\Twig\IconExtension;
use Contena\Frontend\Framework\Twig\TemplateDataExtension;
use Contena\Frontend\Framework\Twig\ThumbnailExtension;
use Contena\Frontend\Framework\Twig\TwigAppVariable;
use Contena\Frontend\Framework\Twig\TwigDateRequestListener;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\MemberGroupRegistration\AbstractMemberGroupRegistrationPageLoader;
use Contena\Frontend\Page\Account\MemberGroupRegistration\MemberGroupRegistrationPageLoader;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoader;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Contena\Frontend\Page\Navigation\Error\ErrorPageLoader;
use Contena\Frontend\Page\Navigation\Error\ErrorPageLoaderInterface;
use Contena\Frontend\Page\Navigation\NavigationPageLoader;
use Contena\Frontend\Page\Navigation\NavigationPageLoaderInterface;
use Contena\Frontend\Page\Robots\Parser\RobotsDirectiveParser;
use Contena\Frontend\Page\Robots\RobotsConfigChangeSubscriber;
use Contena\Frontend\Page\Robots\RobotsPageLoader;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Page\Sitemap\SitemapPageLoader;
use Contena\Frontend\Page\Suggest\SuggestPageLoader;
use Contena\Frontend\Pagelet\Captcha\BasicCaptchaPageletLoader;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoader;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoaderInterface;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoader;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoaderInterface;
use Contena\Frontend\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Contena\Frontend\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Contena\Frontend\Pagelet\Region\RegionDataPageletLoader;
use Contena\Frontend\Storybook\StorybookService;
use Contena\Frontend\System\Member\MemberGroupSubscriber;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('contena.twig.app_variable.allowed_server_params', [
            'server_name',
            'request_uri',
            'app_url',
            'http_user_agent',
            'http_host',
            'server_port',
            'redirect_url',
            'https',
            'forwarded',
            'host',
            'remote_addr',
            'http_x_forwarded_for',
            'http_x_forwarded_host',
            'http_x_forwarded_proto',
            'http_x_forwarded_port',
            'http_x_forwarded_prefix',
        ]);

    $services = $containerConfigurator->services()->defaults()->autowire()->autoconfigure();

    $services->set(ChannelCreateWebCommand::class)
        ->args([
            service('snippet_set.repository'),
            service(ChannelCreator::class),
        ])
        ->tag('console.command');

    $services->set(ApiCaptchaController::class)
        ->public()
        ->arg('$captchas', tagged_iterator('contena.frontend.captcha'))
        ->call('setContainer', [service('service_container')]);

    $services->set(CaptchaController::class)
        ->public()
        ->args([
            service(BasicCaptchaPageletLoader::class),
            service(BasicCaptcha::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(BasicCaptchaPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(BasicCaptchaGenerator::class),
        ]);

    $services->set(CacheCookieEventSubscriber::class)
        ->arg('$sessionFactory', service('session.factory'))
        ->tag('kernel.event_subscriber');

    $services->set(ExtensionThemeDetectionSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CachedDomainLoader::class)
        ->decorate(DomainLoader::class, null, -1000)
        ->args([
            service(CachedDomainLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->set(CachedDomainLoaderInvalidator::class)
        ->arg('$logger', service(CacheInvalidator::class))
        ->tag('kernel.event_subscriber');
    $services->set(DomainLoader::class)
        ->arg('$connection', service(Connection::class));

    $services->set(TenantDefaultDomainLoader::class)
        ->arg('$connection', service(Connection::class));

    $services->set(RequestTransformer::class)
        ->decorate(RequestTransformerInterface::class)
        ->public()
        ->args([
            service(RequestTransformer::class . '.inner'),
            service(SeoResolver::class),
            param('contena.routing.registered_api_prefixes'),
            service(DomainLoader::class),
            service(TenantDefaultDomainLoader::class),
            param('contena.store.use_channel_cookie_path'),
        ]);
    $services->set(Router::class)
        ->decorate('router')
        ->autoconfigure(false)
        ->args([
            service(Router::class . '.inner'),
            service('request_stack'),
            param('frontend.router.allowed_routes'),
        ]);
    $services->set(MaintenanceModeResolver::class)
        ->args([
            service('request_stack'),
            service(CoreMaintenanceModeResolver::class),
        ]);
    $services->set(FrontendRouteEventSubscriber::class)
        ->arg('$dispatcher', service('event_dispatcher'))
        ->tag('kernel.event_subscriber');
    $services->set(TemplateDataSubscriber::class)
        ->args([
            service(HreflangLoaderInterface::class),
            service(ThemeRuntimeConfigService::class),
        ])
        ->tag('kernel.event_subscriber');
    $services->set(FrontendRouteScope::class)
        ->autoconfigure(false)
        ->tag('contena.route_scope');

    $services->set(TemplateDataExtension::class)
        ->args([
            service('request_stack'),
            param('contena.staging.frontend.show_banner'),
            service(Connection::class),
        ])
        ->tag('twig.extension');

    $services->set(IconExtension::class)->tag('twig.extension');
    $services->set(ThumbnailExtension::class)
        ->arg('$finder', service(TemplateFinder::class))
        ->tag('twig.extension');
    $services->set(TwigDateRequestListener::class)
        ->arg('$container', service('service_container'))
        ->tag('kernel.event_listener', ['event' => 'kernel.request']);
    $services->set(ErrorTemplateResolver::class)
        ->arg('$twig', service('twig'));
    $services->set(UrlEncodingTwigFilter::class)->tag('twig.extension');
    $services->set(IconCacheTwigFilter::class)->tag('twig.extension');
    $services->set(TwigAppVariable::class)
        ->decorate('twig.app_variable')
        ->args([
            service(TwigAppVariable::class . '.inner'),
            param('contena.twig.app_variable.allowed_server_params'),
        ]);

    $services->set(FrontendMediaUploader::class)
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service(FrontendMediaValidatorRegistry::class),
        ]);
    $services->set(FrontendMediaValidatorRegistry::class)
        ->public()
        ->arg('$validators', tagged_iterator('frontend.media.upload.validator'));
    $services->set(FrontendMediaImageValidator::class)
        ->tag('frontend.media.upload.validator');
    $services->set(FrontendMediaDocumentValidator::class)
        ->tag('frontend.media.upload.validator');

    $services->set(FrontendSubscriber::class)
        ->args([
            service('request_stack'),
            service('router'),
            service(MaintenanceModeResolver::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BlogListingPageOutOfRangeSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(GenericPageLoader::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);
    $services->alias(GenericPageLoaderInterface::class, GenericPageLoader::class);
    $services->set(NavigationPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(CategoryRoute::class),
            service(BlogListingRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);
    $services->alias(NavigationPageLoaderInterface::class, NavigationPageLoader::class);
    $services->set(MenuOffcanvasPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationLoaderInterface::class),
        ]);
    $services->alias(MenuOffcanvasPageletLoaderInterface::class, MenuOffcanvasPageletLoader::class);
    $services->set(HeaderPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(AbstractLanguageRoute::class),
            service(NavigationLoaderInterface::class),
        ]);
    $services->alias(HeaderPageletLoaderInterface::class, HeaderPageletLoader::class);
    $services->set(FooterPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationLoaderInterface::class),
        ]);
    $services->alias(FooterPageletLoaderInterface::class, FooterPageletLoader::class);
    $services->set(NavigationController::class)
        ->public()
        ->args([
            service(NavigationPageLoader::class),
            service(MenuOffcanvasPageletLoaderInterface::class),
            service(HeaderPageletLoaderInterface::class),
            service(FooterPageletLoaderInterface::class),
            service(CategoryUrlGenerator::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(AbstractContentRoute::class),
            service(ContentRoute::class . '.header.full'),
            service(ContentRoute::class . '.footer.full'),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(ContextController::class)
        ->args([
            service(ContextSwitchRoute::class),
            service('request_stack'),
            service('router.default'),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(AccountLoginPageLoader::class)
        ->args([
            service(GenericPageLoaderInterface::class),
            service('event_dispatcher'),
            service(AbstractCountryRoute::class),
            service(Translator::class),
        ]);
    $services->set(AccountRecoverPasswordPageLoader::class)
        ->args([
            service(GenericPageLoaderInterface::class),
            service('event_dispatcher'),
            service(MemberRecoveryIsExpiredRoute::class),
        ]);
    $services->set(AuthController::class)
        ->public()
        ->args([
            service(AccountLoginPageLoader::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(ResetPasswordRoute::class),
            service(LoginRoute::class),
            service(LogoutRoute::class),
            service(ImitateMemberRoute::class),
            service(AccountRecoverPasswordPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(AccountOverviewPageLoader::class)
        ->args([
            service(GenericPageLoaderInterface::class),
            service('event_dispatcher'),
            service(MemberRoute::class),
            service(Translator::class),
        ]);
    $services->set(AccountProfilePageLoader::class)
        ->args([
            service(GenericPageLoaderInterface::class),
            service('event_dispatcher'),
            service(Translator::class),
        ]);
    $services->set(AccountProfileController::class)
        ->public()
        ->args([
            service(AccountOverviewPageLoader::class),
            service(AccountProfilePageLoader::class),
            service(ChangeMemberProfileRoute::class),
            service(ChangePasswordRoute::class),
            service(ChangeEmailRoute::class),
            service(DeleteMemberRoute::class),
            service('logger'),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(MemberGroupRegistrationPageLoader::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(MemberGroupRegistrationSettingsRoute::class),
            service('event_dispatcher'),
        ]);
    $services->alias(AbstractMemberGroupRegistrationPageLoader::class, MemberGroupRegistrationPageLoader::class);
    $services->set(RegisterController::class)
        ->public()
        ->args([
            service(AccountLoginPageLoader::class),
            service(RegisterRoute::class),
            service(RegisterConfirmRoute::class),
            service(SystemConfigService::class),
            service(AbstractMemberGroupRegistrationPageLoader::class),
            service('channel_domain.repository'),
            service(DoubleSubmitGuard::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(DoubleSubmitGuard::class)
        ->args([
            service('lock.factory'),
            service('cache.double_submit'),
            service('logger'),
        ]);
    $services->set(MemberGroupSubscriber::class)
        ->args([
            service('member_group.repository'),
            service('seo_url.repository'),
            service('language.repository'),
            service(SeoUrlPersister::class),
            service('slugify'),
        ])
        ->tag('kernel.event_subscriber');
    $services->set(AddressDetailPageLoader::class)
        ->args([
            service(GenericPageLoaderInterface::class),
            service(AbstractCountryRoute::class),
            service('event_dispatcher'),
            service(ListAddressRoute::class),
            service(Translator::class),
        ]);
    $services->set(AddressListingPageLoader::class)
        ->args([
            service(GenericPageLoaderInterface::class),
            service(AbstractCountryRoute::class),
            service(ListAddressRoute::class),
            service('event_dispatcher'),
            service(Translator::class),
        ]);
    $services->set(AddressController::class)
        ->public()
        ->args([
            service(AddressListingPageLoader::class),
            service(AddressDetailPageLoader::class),
            service(UpsertAddressRoute::class),
            service(DeleteAddressRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(RegionDataPageletLoader::class)
        ->args([
            service(AbstractRegionRoute::class),
            service('event_dispatcher'),
        ]);
    $services->set(RegionController::class)
        ->public()
        ->args([service(RegionDataPageletLoader::class)])
        ->call('setContainer', [service('service_container')]);
    $services->set(CookieController::class)
        ->public()
        ->args([
            service(CookieRoute::class),
            service(CookieConsentLogRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(ContentSystemPreviewController::class)->public()->call('setContainer', [service('service_container')]);
    $services->set(BlogPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(BlogDetailRoute::class),
            service(CategoryBreadcrumbBuilder::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
        ]);
    $services->set(BlogController::class)
        ->public()
        ->args([
            service(BlogPageLoader::class),
            service(AbstractContentRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(ErrorPageLoader::class)
        ->args([
            service(LandingPageRoute::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);
    $services->alias(ErrorPageLoaderInterface::class, ErrorPageLoader::class);
    $services->set(MaintenancePageLoader::class)
        ->args([
            service(LandingPageRoute::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);
    $services->set(LandingPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(LandingPageRoute::class),
            service('event_dispatcher'),
        ]);
    $services->set(ErrorController::class)->public()->call('setContainer', [service('service_container')]);
    $services->set(MaintenanceController::class)->public()->call('setContainer', [service('service_container')]);
    $services->set(LandingPageController::class)
        ->public()
        ->args([
            service(LandingPageLoader::class),
            service(AbstractContentRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(SearchPageLoader::class)
        ->public()
        ->args([
            service(GenericPageLoader::class),
            service(BlogSearchRoute::class),
            service('event_dispatcher'),
            service(Translator::class),
        ]);
    $services->set(SuggestPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(BlogSuggestRoute::class),
            service(GenericPageLoader::class),
        ]);
    $services->set(SearchController::class)
        ->public()
        ->args([
            service(SearchPageLoader::class),
            service(SuggestPageLoader::class),
            service(BlogSearchRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(RobotsDirectiveParser::class)
        ->args([
            service('event_dispatcher'),
        ]);
    $services->set(RobotsPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service('channel_domain.repository'),
            service(SystemConfigService::class),
            service(RobotsDirectiveParser::class),
        ]);
    $services->set(RobotsConfigChangeSubscriber::class)
        ->args([
            service(RobotsDirectiveParser::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');
    $services->set(RobotsRouteScopeWhitelist::class)
        ->tag('contena.route_scope_whitelist');
    $services->set(RobotsController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);
    $services->set(SitemapPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(SitemapRoute::class),
        ]);
    $services->set(SitemapController::class)
        ->public()
        ->args([
            service(SitemapPageLoader::class),
            service(SitemapFileRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(WellKnownController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(VerificationHashController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(StorybookController::class)
        ->public()
        ->args([
            service('twig'),
            service(StorybookService::class),
        ])
        ->call('setContainer', [service('service_container')]);
    $services->set(StorybookService::class)
        ->args([
            service('channel.blog.repository'),
            service('media.repository'),
            service('channel.repository'),
            service(AbstractChannelContextFactory::class),
            service(DatabaseChannelThemeLoader::class),
            service(ThemeRuntimeConfigStorage::class),
        ]);
    $services->set(StorybookRouteScopeAllowList::class)
        ->tag('contena.route_scope_whitelist');
    $services->set(CanonicalLinkListener::class)
        ->tag('kernel.event_listener', ['event' => BeforeSendResponseEvent::class]);
    $services->set(NotFoundSubscriber::class)
        ->args([
            service('http_kernel'),
            service(ChannelContextRequestRestorer::class),
            param('kernel.debug'),
            service('cache.object'),
            service(EntityCacheKeyGenerator::class),
            service(CacheInvalidator::class),
            service('event_dispatcher'),
            param('session.storage.options'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->set(ResponseHeaderListener::class)
        ->tag('kernel.event_subscriber');
    $services->set(ClearSiteDataListener::class)
        ->arg('$directives', param('frontend.security.clear_site_data_on_logout'))
        ->tag('kernel.event_subscriber');
    $services->set(DomainNotMappedListener::class)
        ->arg('$container', service('service_container'))
        ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

    $services->set(ChannelDomainUtil::class)
        ->args([
            service(RouterInterface::class),
            service(RequestStack::class),
            service(KernelInterface::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(ChannelsReadinessCheck::class)
        ->args([
            service(ChannelDomainUtil::class),
            service(ChannelDomainProvider::class),
        ])
        ->tag('contena.system_check');

    $services->set(BlogDetailReadinessCheck::class)
        ->args([
            service(ChannelDomainUtil::class),
            service(Connection::class),
            service(ChannelDomainProvider::class),
        ])
        ->tag('contena.system_check');

    $services->set(BlogListingReadinessCheck::class)
        ->args([
            service(ChannelDomainUtil::class),
            service(Connection::class),
            service(ChannelDomainProvider::class),
        ])
        ->tag('contena.system_check');

    $services->set(ChannelDomainProvider::class)
        ->arg('$connection', service(Connection::class));

    $services->set(TwigComponentRenderEventListener::class)
        ->arg('$environment', param('kernel.environment'))
        ->autoconfigure(false)
        ->tag('kernel.event_listener');
};
