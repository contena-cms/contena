<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\System\User\Channel\AccountNewsletterRecipientRoute;
use Contena\Core\System\User\Channel\UserGroupRegistrationSettingsRoute;
use Contena\Core\System\User\Channel\UserRecoveryIsExpiredRoute;
use Contena\Core\System\User\Channel\UserRoute;
use Contena\Core\System\User\Channel\ListAddressRoute;
use Contena\Core\System\User\Channel\LoadWishlistRoute;
use Contena\Core\System\User\Validation\AddressValidationFactory;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\NavigationLoader;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoader;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver as CoreDefaultMediaResolver;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\BlogListRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\SeoResolver;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\Channel\SitemapRoute;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\App\Template\TemplateLoader;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\Event\BeforeSendResponseEvent;
use Contena\Core\Framework\Routing\MaintenanceModeResolver as CoreMaintenanceModeResolver;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\Channel\CountryStateRoute;
use Contena\Core\System\Currency\Channel\CurrencyRoute;
use Contena\Core\System\Language\Channel\LanguageRoute;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\ScriptController;
use Contena\Frontend\Event\CartMergedSubscriber;
use Contena\Frontend\Framework\AffiliateTracking\AffiliateTrackingListener;
use Contena\Frontend\Framework\App\Template\IconTemplateLoader;
use Contena\Frontend\Framework\Cache\CacheCookieEventSubscriber;
use Contena\Frontend\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Contena\Frontend\Framework\Command\ChannelCreateFrontendCommand;
use Contena\Frontend\Framework\Cookie\AppCookieProvider;
use Contena\Frontend\Framework\Cookie\CookieProvider;
use Contena\Frontend\Framework\Cookie\CookieProviderInterface;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Media\FrontendMediaUploader;
use Contena\Frontend\Framework\Media\FrontendMediaValidatorRegistry;
use Contena\Frontend\Framework\Media\Validator\FrontendMediaDocumentValidator;
use Contena\Frontend\Framework\Media\Validator\FrontendMediaImageValidator;
use Contena\Frontend\Framework\Routing\CachedDomainLoader;
use Contena\Frontend\Framework\Routing\CachedDomainLoaderInvalidator;
use Contena\Frontend\Framework\Routing\CanonicalLinkListener;
use Contena\Frontend\Framework\Routing\ClearSiteDataListener;
use Contena\Frontend\Framework\Routing\DomainLoader;
use Contena\Frontend\Framework\Routing\DomainNotMappedListener;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Framework\Routing\NotFound\NotFoundSubscriber;
use Contena\Frontend\Framework\Routing\BlogListingPageOutOfRangeSubscriber;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\ResponseHeaderListener;
use Contena\Frontend\Framework\Routing\RobotsRouteScopeWhitelist;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Frontend\Framework\Routing\FrontendRouteEventSubscriber;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\FrontendSubscriber;
use Contena\Frontend\Framework\Routing\StorybookRouteScopeAllowList;
use Contena\Frontend\Framework\Routing\TemplateDataSubscriber;
use Contena\Frontend\Framework\Script\Api\FrontendScriptResponseFactoryFacadeHookFactory;
use Contena\Frontend\Framework\Store\Subscriber\ExtensionThemeDetectionSubscriber;
use Contena\Frontend\Framework\Store\ThemeExtensionRemovalValidator;
use Contena\Frontend\Framework\SystemCheck\BlogDetailReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\BlogListingReadinessCheck;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainContextFactory;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Contena\Frontend\Framework\Twig\Components\TwigComponentRenderEventListener;
use Contena\Frontend\Framework\Twig\ErrorTemplateResolver;
use Contena\Frontend\Framework\Twig\Extension\ConfigExtension;
use Contena\Frontend\Framework\Twig\Extension\IconCacheTwigFilter;
use Contena\Frontend\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Contena\Frontend\Framework\Twig\IconExtension;
use Contena\Frontend\Framework\Twig\TemplateConfigAccessor;
use Contena\Frontend\Framework\Twig\TemplateDataExtension;
use Contena\Frontend\Framework\Twig\ThumbnailExtension;
use Contena\Frontend\Framework\Twig\TwigAppVariable;
use Contena\Frontend\Framework\Twig\TwigDateRequestListener;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoader;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoader;
use Contena\Frontend\Page\Cms\DefaultMediaResolver;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Contena\Frontend\Page\Navigation\NavigationPageLoader;
use Contena\Frontend\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Contena\Frontend\Page\Blog\Configurator\BlogPageConfiguratorLoader;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageLoader;
use Contena\Frontend\Page\Robots\Parser\RobotsDirectiveParser;
use Contena\Frontend\Page\Robots\RobotsConfigChangeSubscriber;
use Contena\Frontend\Page\Robots\RobotsPageLoader;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Page\Sitemap\SitemapPageLoader;
use Contena\Frontend\Page\Suggest\SuggestPageLoader;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoader;
use Contena\Frontend\Page\Wishlist\WishlistPageLoader;
use Contena\Frontend\Pagelet\Captcha\BasicCaptchaPageletLoader;
use Contena\Frontend\Pagelet\Country\CountryStateDataPageletLoader;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoader;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoader;
use Contena\Frontend\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Contena\Frontend\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Contena\Frontend\Storybook\StorybookService;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\Mail\MailThemeConfigSubscriber;
use Contena\Frontend\Theme\Mail\MailThemeIdLoader;
use Contena\Frontend\Theme\ResolvedConfigLoader;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\ThemeConfigValueAccessor;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeRuntimeConfigStorage;
use Contena\Frontend\Theme\ThemeScripts;
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
            'server_name',
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

    $services = $containerConfigurator->services();

    // Checkout
    $services->set(FrontendCartFacade::class)
        ->args([
            service(CartService::class),
            service(BlockedShippingMethodSwitcher::class),
            service(BlockedPaymentMethodSwitcher::class),
            service(ContextSwitchRoute::class),
            service(CartCalculator::class),
            service(CartPersister::class),
            service(CheckoutGatewayRoute::class),
        ]);

    $services->set(UserGroupSubscriber::class)
        ->args([
            service('user_group.repository'),
            service('seo_url.repository'),
            service('language.repository'),
            service(SeoUrlPersister::class),
            service('slugify'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BlockedShippingMethodSwitcher::class)
        ->args([
            service(ShippingMethodRoute::class),
        ]);

    $services->set(BlockedPaymentMethodSwitcher::class)
        ->args([
            service(PaymentMethodRoute::class),
        ]);

    $services->set(CacheCookieEventSubscriber::class)
        ->args([
            service('session.factory'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DoubleSubmitGuard::class)
        ->args([
            service('lock.factory'),
            service('cache.double_submit'),
            service('logger'),
        ]);

    $services->set(FrontendScriptResponseFactoryFacadeHookFactory::class)
        ->public()
        ->args([
            service('router'),
            service(ScriptController::class),
        ]);

    $services->set(ExtensionThemeDetectionSubscriber::class)
        ->args([
            service('theme.repository'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ThemeExtensionRemovalValidator::class)
        ->args([
            service('theme.repository'),
            service('channel.repository'),
        ])
        ->tag('contena.store.extension_removal_validator');

    $services->set(CachedDomainLoader::class)
        ->decorate(DomainLoader::class, null, -1000)
        ->args([
            service(CachedDomainLoader::class . '.inner'),
            service('cache.object'),
            service('logger'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CachedDomainLoaderInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DomainLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(RequestTransformer::class)
        ->decorate(RequestTransformerInterface::class)
        ->public()
        ->args([
            service(RequestTransformer::class . '.inner'),
            service(SeoResolver::class),
            param('contena.routing.registered_api_prefixes'),
            service(DomainLoader::class),
            param('contena.routing.api_context_route_prefixes'),
        ]);

    $services->set(Router::class)
        ->decorate('router')
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
        ->args([
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(FrontendRouteScope::class)
        ->tag('contena.route_scope');

    $services->set(TemplateDataExtension::class)
        ->args([
            service('request_stack'),
            param('contena.staging.frontend.show_banner'),
            service(Connection::class),
        ])
        ->tag('twig.extension');

    $services->set(TemplateConfigAccessor::class)
        ->args([
            service(SystemConfigService::class),
            service(ThemeConfigValueAccessor::class),
            service(ThemeScripts::class),
            param('kernel.environment'),
            tagged_iterator('contena.asset', 'asset'),
        ]);

    $services->set(MailThemeConfigSubscriber::class)
        ->args([
            service(ChannelContextFactory::class),
            service(MailThemeIdLoader::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MailThemeIdLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ThemeConfigValueAccessor::class)
        ->args([
            service(ResolvedConfigLoader::class),
            service(CacheTagCollector::class),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ConfigExtension::class)
        ->args([
            service(TemplateConfigAccessor::class),
        ])
        ->tag('twig.extension');

    $services->set(IconExtension::class)
        ->tag('twig.extension');

    $services->set(ThumbnailExtension::class)
        ->args([
            service(TemplateFinder::class),
        ])
        ->tag('twig.extension');

    $services->set(TwigDateRequestListener::class)
        ->args([
            service('service_container'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request']);

    $services->set(ErrorTemplateResolver::class)
        ->args([
            service('twig'),
        ]);

    $services->set(UrlEncodingTwigFilter::class)
        ->tag('twig.extension');

    $services->set(IconCacheTwigFilter::class)
        ->tag('twig.extension');

    $services->set(FrontendMediaUploader::class)
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service(FrontendMediaValidatorRegistry::class),
        ]);

    $services->set(FrontendMediaValidatorRegistry::class)
        ->public()
        ->args([
            tagged_iterator('frontend.media.upload.validator'),
        ]);

    $services->set(FrontendMediaImageValidator::class)
        ->tag('frontend.media.upload.validator');

    $services->set(FrontendMediaDocumentValidator::class)
        ->tag('frontend.media.upload.validator');

    $services->set(FrontendSubscriber::class)
        ->args([
            service('router'),
            service(MaintenanceModeResolver::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BlogListingPageOutOfRangeSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(TemplateDataSubscriber::class)
        ->args([
            service(HreflangLoaderInterface::class),
            service(ShopIdProvider::class),
            service(ActiveAppsLoader::class),
            service(ThemeRuntimeConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

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

    $services->set(AffiliateTrackingListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(NavigationPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(CategoryRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(ErrorPageLoader::class)
        ->args([
            service(ChannelCmsPageLoader::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(MaintenancePageLoader::class)
        ->args([
            service(ChannelCmsPageLoader::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(LandingPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(LandingPageRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(MenuOffcanvasPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationLoader::class),
        ]);

    $services->set(BasicCaptchaPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(BasicCaptchaGenerator::class),
            service(NavigationLoader::class),
        ]);

    $services->set(CountryStateDataPageletLoader::class)
        ->args([
            service(CountryStateRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(SuggestPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(BlogSuggestRoute::class),
            service(GenericPageLoader::class),
        ]);

    $services->set(HeaderPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(CurrencyRoute::class),
            service(LanguageRoute::class),
            service(NavigationLoader::class),
        ]);

    $services->set(FooterPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationLoader::class),
            service(PaymentMethodRoute::class),
            service(ShippingMethodRoute::class),
        ]);

    $services->set(GenericPageLoader::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(SearchPageLoader::class)
        ->public()
        ->args([
            service(GenericPageLoader::class),
            service(BlogSearchRoute::class),
            service('event_dispatcher'),
            service(Translator::class),
        ]);

    $services->set(BlogPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(BlogDetailRoute::class),
            service('blog_review.repository'),
            service(SystemConfigService::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(MinimalQuickViewPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(BlogDetailRoute::class),
        ]);

    $services->set(BlogPageConfiguratorLoader::class)
        ->decorate(BlogConfiguratorLoader::class)
        ->args([
            service(BlogPageConfiguratorLoader::class . '.inner'),
        ]);

    $services->set(CheckoutFinishPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(GenericPageLoader::class),
            service(OrderRoute::class),
            service(Translator::class),
            service(SystemConfigService::class),
            service(OrderBlogAvailabilityResolver::class),
        ]);

    $services->set(CheckoutConfirmPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(FrontendCartFacade::class),
            service(GenericPageLoader::class),
            service(AddressValidationFactory::class),
            service(DataValidator::class),
            service(Translator::class),
        ]);

    $services->set(CheckoutCartPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(FrontendCartFacade::class),
            service(CountryRoute::class),
            service(Translator::class),
        ]);

    $services->set(OffcanvasCartPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(FrontendCartFacade::class),
            service(GenericPageLoader::class),
            service(ShippingMethodRoute::class),
        ]);

    $services->set(AccountProfilePageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(SalutationRoute::class),
            service(AbstractSalutationsSorter::class),
            service(Translator::class),
        ]);

    $services->set(AccountOverviewPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(UserRoute::class),
            service(NewsletterAccountPageletLoader::class),
            service(Translator::class),
            service(OrderBlogAvailabilityResolver::class),
        ]);

    $services->set(AccountOrderPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(Translator::class),
            service(OrderBlogAvailabilityResolver::class),
        ]);

    $services->set(OrderBlogAvailabilityResolver::class)
        ->args([
            service('channel.blog.repository'),
            service(SystemConfigService::class),
            service(BlogCloseoutFilterFactory::class),
        ]);

    $services->set(AccountOrderDetailPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
        ])
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(AccountEditOrderPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(CheckoutGatewayRoute::class),
            service(OrderRestorer::class),
            service(OrderService::class),
            service(Translator::class),
        ]);

    $services->set(AccountLoginPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(CountryRoute::class),
            service(SalutationRoute::class),
            service(AbstractSalutationsSorter::class),
            service(Translator::class),
        ]);

    $services->set(AccountRecoverPasswordPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(UserRecoveryIsExpiredRoute::class),
        ]);

    $services->set(UserGroupRegistrationPageLoader::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(UserGroupRegistrationSettingsRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(CheckoutRegisterPageLoader::class)
        ->public()
        ->args([
            service(GenericPageLoader::class),
            service(ListAddressRoute::class),
            service('event_dispatcher'),
            service(CartService::class),
            service(SalutationRoute::class),
            service(CountryRoute::class),
            service(Translator::class),
            service(AbstractSalutationsSorter::class),
        ]);

    $services->set(NewsletterSubscribePageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(NewsletterAccountPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NewsletterSubscribeRoute::class),
            service(NewsletterUnsubscribeRoute::class),
            service(AccountNewsletterRecipientRoute::class),
            service(Translator::class),
            service(SystemConfigService::class),
        ]);

    $services->set(AddressDetailPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(CountryRoute::class),
            service(SalutationRoute::class),
            service('event_dispatcher'),
            service(ListAddressRoute::class),
            service(AbstractSalutationsSorter::class),
            service(Translator::class),
        ]);

    $services->set(AddressListingPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(CountryRoute::class),
            service(SalutationRoute::class),
            service(ListAddressRoute::class),
            service('event_dispatcher'),
            service(CartService::class),
            service(Translator::class),
            service(AbstractSalutationsSorter::class),
        ]);

    $services->set(SitemapPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(SitemapRoute::class),
        ]);

    $services->set(DefaultMediaResolver::class)
        ->decorate(CoreDefaultMediaResolver::class)
        ->args([
            service(DefaultMediaResolver::class . '.inner'),
            service(Translator::class),
            service('assets.packages'),
        ]);

    $services->set(ChannelCreateFrontendCommand::class)
        ->args([
            service('snippet_set.repository'),
            service(ChannelCreator::class),
        ])
        ->tag('console.command');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(CookieProviderInterface::class, CookieProvider::class)
        ->deprecate('contena/frontend', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0. Use the CookieGroupCollectEvent instead to introduce cookies.')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(AppCookieProvider::class)
        ->decorate(CookieProviderInterface::class)
        ->args([
            service('.inner'),
        ])
        ->deprecate('contena/frontend', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0 without replacement')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ResponseHeaderListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ClearSiteDataListener::class)
        ->args([
            param('frontend.security.clear_site_data_on_logout'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CartMergedSubscriber::class)
        ->args([
            service('translator'),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WishlistPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(LoadWishlistRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(GuestWishlistPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(GuestWishlistPageletLoader::class)
        ->args([
            service(BlogListRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(BlogCloseoutFilterFactory::class),
        ]);

    $services->set(IconTemplateLoader::class)
        ->decorate(TemplateLoader::class)
        ->args([
            service(IconTemplateLoader::class . '.inner'),
            service(FrontendPluginConfigurationFactory::class),
            service(SourceResolver::class),
            param('kernel.project_dir'),
        ]);

    $services->set(TwigAppVariable::class)
        ->decorate('twig.app_variable')
        ->args([
            service(TwigAppVariable::class . '.inner'),
            param('contena.twig.app_variable.allowed_server_params'),
        ]);

    $services->set(DomainNotMappedListener::class)
        ->args([
            service('service_container'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

    $services->set(ChannelDomainUtil::class)
        ->args([
            service(RouterInterface::class),
            service(RequestStack::class),
            service(KernelInterface::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(ChannelReadinessCheck::class)
        ->args([
            service(ChannelDomainUtil::class),
            service(ChannelDomainProvider::class),
        ])
        ->tag('contena.system_check');

    $services->set(BlogDetailReadinessCheck::class)
        ->args([
            service(ChannelDomainUtil::class),
            service(ChannelDomainProvider::class),
            service('channel.blog.repository'),
            service(ChannelDomainContextFactory::class),
            service(BlogCloseoutFilterFactory::class),
            service(SystemConfigService::class),
        ])
        ->tag('contena.system_check');

    $services->set(BlogListingReadinessCheck::class)
        ->args([
            service(ChannelDomainUtil::class),
            service(Connection::class),
            service(ChannelDomainProvider::class),
            service('channel.category.repository'),
            service(ChannelDomainContextFactory::class),
        ])
        ->tag('contena.system_check');

    $services->set(ChannelDomainProvider::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ChannelDomainContextFactory::class)
        ->args([
            service(ChannelContextFactory::class),
            service(CartRuleLoader::class),
            service(CartFactory::class),
        ]);

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

    $services->set(TwigComponentRenderEventListener::class)
        ->args([
            param('kernel.environment'),
        ])
        ->tag('kernel.event_listener');

    $services->set(StorybookService::class)
        ->args([
            service('channel.blog.repository'),
            service('media.repository'),
            service('channel.repository'),
            service(ChannelContextFactory::class),
            service(DatabaseChannelThemeLoader::class),
            service(ThemeRuntimeConfigStorage::class),
        ]);

    $services->set(StorybookRouteScopeAllowList::class)
        ->tag('contena.route_scope_whitelist');
};
