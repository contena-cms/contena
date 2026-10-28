<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\CartFactory;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Checkout\Cart\Order\OrderRestorer;
use Contena\Core\Checkout\Cart\SalesChannel\CartService;
use Contena\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientRoute;
use Contena\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRoute;
use Contena\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Contena\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Contena\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Contena\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Contena\Core\Checkout\Order\SalesChannel\OrderRoute;
use Contena\Core\Checkout\Order\SalesChannel\OrderService;
use Contena\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Contena\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\NavigationLoader;
use Contena\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Contena\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver as CoreDefaultMediaResolver;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Contena\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Contena\Core\Content\Product\SalesChannel\ProductListRoute;
use Contena\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Contena\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\SeoResolver;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\App\Template\TemplateLoader;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\Event\BeforeSendResponseEvent;
use Contena\Core\Framework\Routing\MaintenanceModeResolver as CoreMaintenanceModeResolver;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Contena\Core\System\Country\SalesChannel\CountryRoute;
use Contena\Core\System\Country\SalesChannel\CountryStateRoute;
use Contena\Core\System\Currency\SalesChannel\CurrencyRoute;
use Contena\Core\System\Language\SalesChannel\LanguageRoute;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Contena\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Contena\Core\System\Salutation\AbstractSalutationsSorter;
use Contena\Core\System\Salutation\SalesChannel\SalutationRoute;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Contena\Storefront\Checkout\Customer\CustomerGroupSubscriber;
use Contena\Storefront\Checkout\Order\OrderProductAvailabilityResolver;
use Contena\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Contena\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Contena\Storefront\Controller\ScriptController;
use Contena\Storefront\Event\CartMergedSubscriber;
use Contena\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Contena\Storefront\Framework\App\Template\IconTemplateLoader;
use Contena\Storefront\Framework\Cache\CacheCookieEventSubscriber;
use Contena\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Contena\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand;
use Contena\Storefront\Framework\Cookie\AppCookieProvider;
use Contena\Storefront\Framework\Cookie\CookieProvider;
use Contena\Storefront\Framework\Cookie\CookieProviderInterface;
use Contena\Storefront\Framework\Guard\DoubleSubmitGuard;
use Contena\Storefront\Framework\Media\StorefrontMediaUploader;
use Contena\Storefront\Framework\Media\StorefrontMediaValidatorRegistry;
use Contena\Storefront\Framework\Media\Validator\StorefrontMediaDocumentValidator;
use Contena\Storefront\Framework\Media\Validator\StorefrontMediaImageValidator;
use Contena\Storefront\Framework\Routing\CachedDomainLoader;
use Contena\Storefront\Framework\Routing\CachedDomainLoaderInvalidator;
use Contena\Storefront\Framework\Routing\CanonicalLinkListener;
use Contena\Storefront\Framework\Routing\ClearSiteDataListener;
use Contena\Storefront\Framework\Routing\DomainLoader;
use Contena\Storefront\Framework\Routing\DomainNotMappedListener;
use Contena\Storefront\Framework\Routing\MaintenanceModeResolver;
use Contena\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;
use Contena\Storefront\Framework\Routing\ProductListingPageOutOfRangeSubscriber;
use Contena\Storefront\Framework\Routing\RequestTransformer;
use Contena\Storefront\Framework\Routing\ResponseHeaderListener;
use Contena\Storefront\Framework\Routing\RobotsRouteScopeWhitelist;
use Contena\Storefront\Framework\Routing\Router;
use Contena\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Contena\Storefront\Framework\Routing\StorefrontRouteScope;
use Contena\Storefront\Framework\Routing\StorefrontSubscriber;
use Contena\Storefront\Framework\Routing\StorybookRouteScopeAllowList;
use Contena\Storefront\Framework\Routing\TemplateDataSubscriber;
use Contena\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacadeHookFactory;
use Contena\Storefront\Framework\Store\Subscriber\ExtensionThemeDetectionSubscriber;
use Contena\Storefront\Framework\Store\ThemeExtensionRemovalValidator;
use Contena\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;
use Contena\Storefront\Framework\SystemCheck\ProductListingReadinessCheck;
use Contena\Storefront\Framework\SystemCheck\SalesChannelsReadinessCheck;
use Contena\Storefront\Framework\SystemCheck\Util\SalesChannelDomainContextFactory;
use Contena\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;
use Contena\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Contena\Storefront\Framework\Twig\Components\TwigComponentRenderEventListener;
use Contena\Storefront\Framework\Twig\ErrorTemplateResolver;
use Contena\Storefront\Framework\Twig\Extension\ConfigExtension;
use Contena\Storefront\Framework\Twig\Extension\IconCacheTwigFilter;
use Contena\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Contena\Storefront\Framework\Twig\IconExtension;
use Contena\Storefront\Framework\Twig\TemplateConfigAccessor;
use Contena\Storefront\Framework\Twig\TemplateDataExtension;
use Contena\Storefront\Framework\Twig\ThumbnailExtension;
use Contena\Storefront\Framework\Twig\TwigAppVariable;
use Contena\Storefront\Framework\Twig\TwigDateRequestListener;
use Contena\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Contena\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Contena\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Contena\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Contena\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Contena\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Contena\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Contena\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Contena\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Contena\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Contena\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Contena\Storefront\Page\Cms\DefaultMediaResolver;
use Contena\Storefront\Page\GenericPageLoader;
use Contena\Storefront\Page\LandingPage\LandingPageLoader;
use Contena\Storefront\Page\Maintenance\MaintenancePageLoader;
use Contena\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Contena\Storefront\Page\Navigation\NavigationPageLoader;
use Contena\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Contena\Storefront\Page\Product\Configurator\ProductPageConfiguratorLoader;
use Contena\Storefront\Page\Product\ProductPageLoader;
use Contena\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Contena\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Contena\Storefront\Page\Robots\RobotsConfigChangeSubscriber;
use Contena\Storefront\Page\Robots\RobotsPageLoader;
use Contena\Storefront\Page\Search\SearchPageLoader;
use Contena\Storefront\Page\Sitemap\SitemapPageLoader;
use Contena\Storefront\Page\Suggest\SuggestPageLoader;
use Contena\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Contena\Storefront\Page\Wishlist\WishlistPageLoader;
use Contena\Storefront\Pagelet\Captcha\BasicCaptchaPageletLoader;
use Contena\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Contena\Storefront\Pagelet\Footer\FooterPageletLoader;
use Contena\Storefront\Pagelet\Header\HeaderPageletLoader;
use Contena\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Contena\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Contena\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Contena\Storefront\Storybook\StorybookService;
use Contena\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Contena\Storefront\Theme\Mail\MailThemeConfigSubscriber;
use Contena\Storefront\Theme\Mail\MailThemeIdLoader;
use Contena\Storefront\Theme\ResolvedConfigLoader;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Contena\Storefront\Theme\ThemeConfigValueAccessor;
use Contena\Storefront\Theme\ThemeRuntimeConfigService;
use Contena\Storefront\Theme\ThemeRuntimeConfigStorage;
use Contena\Storefront\Theme\ThemeScripts;
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
    $services->set(StorefrontCartFacade::class)
        ->args([
            service(CartService::class),
            service(BlockedShippingMethodSwitcher::class),
            service(BlockedPaymentMethodSwitcher::class),
            service(ContextSwitchRoute::class),
            service(CartCalculator::class),
            service(CartPersister::class),
            service(CheckoutGatewayRoute::class),
        ]);

    $services->set(CustomerGroupSubscriber::class)
        ->args([
            service('customer_group.repository'),
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

    $services->set(StorefrontScriptResponseFactoryFacadeHookFactory::class)
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
            service('sales_channel.repository'),
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
            param('storefront.router.allowed_routes'),
        ]);

    $services->set(MaintenanceModeResolver::class)
        ->args([
            service('request_stack'),
            service(CoreMaintenanceModeResolver::class),
        ]);

    $services->set(StorefrontRouteEventSubscriber::class)
        ->args([
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StorefrontRouteScope::class)
        ->tag('contena.route_scope');

    $services->set(TemplateDataExtension::class)
        ->args([
            service('request_stack'),
            param('contena.staging.storefront.show_banner'),
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
            service(SalesChannelContextFactory::class),
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

    $services->set(StorefrontMediaUploader::class)
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service(StorefrontMediaValidatorRegistry::class),
        ]);

    $services->set(StorefrontMediaValidatorRegistry::class)
        ->public()
        ->args([
            tagged_iterator('storefront.media.upload.validator'),
        ]);

    $services->set(StorefrontMediaImageValidator::class)
        ->tag('storefront.media.upload.validator');

    $services->set(StorefrontMediaDocumentValidator::class)
        ->tag('storefront.media.upload.validator');

    $services->set(StorefrontSubscriber::class)
        ->args([
            service('router'),
            service(MaintenanceModeResolver::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductListingPageOutOfRangeSubscriber::class)
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
            service(SalesChannelContextRequestRestorer::class),
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
            service(SalesChannelCmsPageLoader::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(MaintenancePageLoader::class)
        ->args([
            service(SalesChannelCmsPageLoader::class),
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
            service(ProductSuggestRoute::class),
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
            service(ProductSearchRoute::class),
            service('event_dispatcher'),
            service(Translator::class),
        ]);

    $services->set(ProductPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(ProductDetailRoute::class),
            service('product_review.repository'),
            service(SystemConfigService::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(MinimalQuickViewPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(ProductDetailRoute::class),
        ]);

    $services->set(ProductPageConfiguratorLoader::class)
        ->decorate(ProductConfiguratorLoader::class)
        ->args([
            service(ProductPageConfiguratorLoader::class . '.inner'),
        ]);

    $services->set(CheckoutFinishPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(GenericPageLoader::class),
            service(OrderRoute::class),
            service(Translator::class),
            service(SystemConfigService::class),
            service(OrderProductAvailabilityResolver::class),
        ]);

    $services->set(CheckoutConfirmPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(StorefrontCartFacade::class),
            service(GenericPageLoader::class),
            service(AddressValidationFactory::class),
            service(DataValidator::class),
            service(Translator::class),
        ]);

    $services->set(CheckoutCartPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(StorefrontCartFacade::class),
            service(CountryRoute::class),
            service(Translator::class),
        ]);

    $services->set(OffcanvasCartPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(StorefrontCartFacade::class),
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
            service(CustomerRoute::class),
            service(NewsletterAccountPageletLoader::class),
            service(Translator::class),
            service(OrderProductAvailabilityResolver::class),
        ]);

    $services->set(AccountOrderPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(Translator::class),
            service(OrderProductAvailabilityResolver::class),
        ]);

    $services->set(OrderProductAvailabilityResolver::class)
        ->args([
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
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
            service(CustomerRecoveryIsExpiredRoute::class),
        ]);

    $services->set(CustomerGroupRegistrationPageLoader::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(CustomerGroupRegistrationSettingsRoute::class),
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

    $services->set(SalesChannelCreateStorefrontCommand::class)
        ->args([
            service('snippet_set.repository'),
            service(SalesChannelCreator::class),
        ])
        ->tag('console.command');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(CookieProviderInterface::class, CookieProvider::class)
        ->deprecate('contena/storefront', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0. Use the CookieGroupCollectEvent instead to introduce cookies.')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(AppCookieProvider::class)
        ->decorate(CookieProviderInterface::class)
        ->args([
            service('.inner'),
        ])
        ->deprecate('contena/storefront', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0 without replacement')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ResponseHeaderListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ClearSiteDataListener::class)
        ->args([
            param('storefront.security.clear_site_data_on_logout'),
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
            service(ProductListRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ProductCloseoutFilterFactory::class),
        ]);

    $services->set(IconTemplateLoader::class)
        ->decorate(TemplateLoader::class)
        ->args([
            service(IconTemplateLoader::class . '.inner'),
            service(StorefrontPluginConfigurationFactory::class),
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

    $services->set(SalesChannelDomainUtil::class)
        ->args([
            service(RouterInterface::class),
            service(RequestStack::class),
            service(KernelInterface::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(SalesChannelsReadinessCheck::class)
        ->args([
            service(SalesChannelDomainUtil::class),
            service(SalesChannelDomainProvider::class),
        ])
        ->tag('contena.system_check');

    $services->set(ProductDetailReadinessCheck::class)
        ->args([
            service(SalesChannelDomainUtil::class),
            service(SalesChannelDomainProvider::class),
            service('sales_channel.product.repository'),
            service(SalesChannelDomainContextFactory::class),
            service(ProductCloseoutFilterFactory::class),
            service(SystemConfigService::class),
        ])
        ->tag('contena.system_check');

    $services->set(ProductListingReadinessCheck::class)
        ->args([
            service(SalesChannelDomainUtil::class),
            service(Connection::class),
            service(SalesChannelDomainProvider::class),
            service('sales_channel.category.repository'),
            service(SalesChannelDomainContextFactory::class),
        ])
        ->tag('contena.system_check');

    $services->set(SalesChannelDomainProvider::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SalesChannelDomainContextFactory::class)
        ->args([
            service(SalesChannelContextFactory::class),
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
            service('sales_channel_domain.repository'),
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
            service('sales_channel.product.repository'),
            service('media.repository'),
            service('sales_channel.repository'),
            service(SalesChannelContextFactory::class),
            service(DatabaseSalesChannelThemeLoader::class),
            service(ThemeRuntimeConfigStorage::class),
        ]);

    $services->set(StorybookRouteScopeAllowList::class)
        ->tag('contena.route_scope_whitelist');
};
