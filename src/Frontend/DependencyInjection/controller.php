<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use Contena\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Contena\Core\Checkout\Cart\LineItemFactoryRegistry;
use Contena\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Contena\Core\Checkout\Cart\SalesChannel\CartReorderRoute;
use Contena\Core\Checkout\Cart\SalesChannel\CartService;
use Contena\Core\Checkout\Customer\SalesChannel\AccountService;
use Contena\Core\Checkout\Customer\SalesChannel\AddWishlistProductRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Contena\Core\Checkout\Customer\SalesChannel\DeleteAddressRoute;
use Contena\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute;
use Contena\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Contena\Core\Checkout\Customer\SalesChannel\MergeWishlistProductRoute;
use Contena\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Contena\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Contena\Core\Checkout\Customer\SalesChannel\RemoveWishlistProductRoute;
use Contena\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Contena\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Contena\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Contena\Core\Checkout\DocumentV2\SalesChannel\DocumentRoute;
use Contena\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Contena\Core\Checkout\Order\SalesChannel\OrderRoute;
use Contena\Core\Checkout\Order\SalesChannel\OrderService;
use Contena\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Contena\Core\Checkout\Payment\PaymentProcessor;
use Contena\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\Cms\SalesChannel\CmsRoute;
use Contena\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Contena\Core\Content\Cookie\SalesChannel\CookieRoute;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Contena\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Contena\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Contena\Core\Content\Product\SalesChannel\ProductListRoute;
use Contena\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Contena\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Contena\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Contena\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Contena\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Contena\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Contena\Core\Framework\App\Api\AppJWTGenerateRoute;
use Contena\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Contena\Core\Framework\Script\Api\ScriptResponseEncoder;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Contena\Storefront\Controller\AccountOrderController;
use Contena\Storefront\Controller\AccountProfileController;
use Contena\Storefront\Controller\AddressController;
use Contena\Storefront\Controller\Api\CaptchaController as ApiCaptchaController;
use Contena\Storefront\Controller\AppController;
use Contena\Storefront\Controller\AuthController;
use Contena\Storefront\Controller\CaptchaController;
use Contena\Storefront\Controller\CartLineItemController;
use Contena\Storefront\Controller\CheckoutController;
use Contena\Storefront\Controller\CmsController;
use Contena\Storefront\Controller\ContextController;
use Contena\Storefront\Controller\ContextGatewayController;
use Contena\Storefront\Controller\CookieController;
use Contena\Storefront\Controller\CountryStateController;
use Contena\Storefront\Controller\DocumentController;
use Contena\Storefront\Controller\DownloadController;
use Contena\Storefront\Controller\ErrorController;
use Contena\Storefront\Controller\FormController;
use Contena\Storefront\Controller\LandingPageController;
use Contena\Storefront\Controller\MaintenanceController;
use Contena\Storefront\Controller\NavigationController;
use Contena\Storefront\Controller\NewsletterController;
use Contena\Storefront\Controller\ProductController;
use Contena\Storefront\Controller\RegisterController;
use Contena\Storefront\Controller\RobotsController;
use Contena\Storefront\Controller\ScriptController;
use Contena\Storefront\Controller\SearchController;
use Contena\Storefront\Controller\SitemapController;
use Contena\Storefront\Controller\StorybookController;
use Contena\Storefront\Controller\VerificationHashController;
use Contena\Storefront\Controller\WellKnownController;
use Contena\Storefront\Controller\WishlistController;
use Contena\Storefront\Framework\Captcha\BasicCaptcha;
use Contena\Storefront\Framework\Guard\DoubleSubmitGuard;
use Contena\Storefront\Framework\Routing\MaintenanceModeResolver;
use Contena\Storefront\Framework\Twig\ErrorTemplateResolver;
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
use Contena\Storefront\Page\GenericPageLoader;
use Contena\Storefront\Page\LandingPage\LandingPageLoader;
use Contena\Storefront\Page\Maintenance\MaintenancePageLoader;
use Contena\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Contena\Storefront\Page\Navigation\NavigationPageLoader;
use Contena\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Contena\Storefront\Page\Product\ProductPageLoader;
use Contena\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->public();

    $services->set(ApiCaptchaController::class)
        ->public()
        ->args([
            tagged_iterator('contena.storefront.captcha'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AccountOrderController::class)
        ->args([
            service(AccountOrderPageLoader::class),
            service(AccountEditOrderPageLoader::class),
            service(ContextSwitchRoute::class),
            service(CancelOrderRoute::class),
            service(SetPaymentOrderRoute::class),
            service(HandlePaymentMethodRoute::class),
            service('event_dispatcher'),
            service(AccountOrderDetailPageLoader::class)->nullOnInvalid(),
            service(OrderRoute::class),
            service(SalesChannelContextService::class),
            service(SystemConfigService::class),
            service(OrderService::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AccountProfileController::class)
        ->args([
            service(AccountOverviewPageLoader::class),
            service(AccountProfilePageLoader::class),
            service(ChangeCustomerProfileRoute::class),
            service(ChangePasswordRoute::class),
            service(ChangeEmailRoute::class),
            service(DeleteCustomerRoute::class),
            service('logger'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AddressController::class)
        ->args([
            service(AddressListingPageLoader::class),
            service(AddressDetailPageLoader::class),
            service(AccountService::class),
            service(ListAddressRoute::class),
            service(UpsertAddressRoute::class),
            service(DeleteAddressRoute::class),
            service(ContextSwitchRoute::class),
            service(SalesChannelContextService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(ResetPasswordRoute::class),
            service(LoginRoute::class),
            service(LogoutRoute::class),
            service(ImitateCustomerRoute::class),
            service(StorefrontCartFacade::class),
            service(AccountRecoverPasswordPageLoader::class),
            service(ConvertGuestRoute::class),
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AppController::class)
        ->public()
        ->args([
            service(AppJWTGenerateRoute::class),
        ]);

    $services->set(CartLineItemController::class)
        ->args([
            service(CartService::class),
            service(ProductLineItemFactory::class),
            service(HtmlSanitizer::class),
            service(ProductListRoute::class),
            service(LineItemFactoryRegistry::class),
            service(CartReorderRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CheckoutController::class)
        ->args([
            service(CartService::class),
            service(CheckoutCartPageLoader::class),
            service(CheckoutConfirmPageLoader::class),
            service(CheckoutFinishPageLoader::class),
            service(OrderService::class),
            service(PaymentProcessor::class),
            service(OffcanvasCartPageLoader::class),
            service(LogoutRoute::class),
            service(CartLoadRoute::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ContextGatewayController::class)
        ->args([
            service(ContextGatewayRoute::class),
            service(CartService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CookieController::class)
        ->args([
            service(CookieRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CmsController::class)
        ->args([
            service(CmsRoute::class),
            service(CategoryRoute::class),
            service(ProductListingRoute::class),
            service(ProductDetailRoute::class),
            service(ProductReviewLoader::class),
            service(FindProductVariantRoute::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(FormController::class)
        ->args([
            service(ContactFormRoute::class),
            service(NewsletterSubscribeRoute::class),
            service(NewsletterUnsubscribeRoute::class),
            service(RevocationRequestRoute::class),
            service(ConstraintViolationTranslator::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ContextController::class)
        ->args([
            service(ContextSwitchRoute::class),
            service('request_stack'),
            service('router.default'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(MaintenanceController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(MaintenancePageLoader::class),
            service(MaintenanceModeResolver::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ErrorController::class)
        ->public()
        ->args([
            service(ErrorTemplateResolver::class),
            service(SystemConfigService::class),
            service(ErrorPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NavigationController::class)
        ->args([
            service(NavigationPageLoader::class),
            service(MenuOffcanvasPageletLoader::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
            service(CategoryUrlGenerator::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NewsletterController::class)
        ->args([
            service(NewsletterSubscribePageLoader::class),
            service(NewsletterConfirmRoute::class),
            service(NewsletterAccountPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ProductController::class)
        ->args([
            service(ProductPageLoader::class),
            service(FindProductVariantRoute::class),
            service(MinimalQuickViewPageLoader::class),
            service(ProductReviewSaveRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(ProductReviewLoader::class),
            service(ProductPurchaseLimitRoute::class),
            service(GaranLabelRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(LandingPageController::class)
        ->args([
            service(LandingPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(RegisterController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(RegisterRoute::class),
            service(RegisterConfirmRoute::class),
            service(CartService::class),
            service(CheckoutRegisterPageLoader::class),
            service(SystemConfigService::class),
            service('customer.repository'),
            service(CustomerGroupRegistrationPageLoader::class),
            service('sales_channel_domain.repository'),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
            service(DoubleSubmitGuard::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ScriptController::class)
        ->args([
            service(GenericPageLoader::class),
            service(ScriptResponseEncoder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SearchController::class)
        ->args([
            service(SearchPageLoader::class),
            service(SuggestPageLoader::class),
            service(ProductSearchRoute::class),
            param('contena.storefront.redirect_on_single_hit_fields'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SitemapController::class)
        ->args([
            service(SitemapPageLoader::class),
            service(SitemapFileRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CountryStateController::class)
        ->public()
        ->args([
            service(CountryStateDataPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DocumentController::class)
        ->public()
        ->args([
            service(DocumentRoute::class),
            service(LogoutRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DownloadController::class)
        ->public()
        ->args([
            service(DownloadRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(WellKnownController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(WishlistController::class)
        ->public()
        ->args([
            service(WishlistPageLoader::class),
            service(LoadWishlistRoute::class),
            service(AddWishlistProductRoute::class),
            service(RemoveWishlistProductRoute::class),
            service(MergeWishlistProductRoute::class),
            service(GuestWishlistPageLoader::class),
            service(GuestWishlistPageletLoader::class),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CaptchaController::class)
        ->public()
        ->args([
            service(BasicCaptchaPageletLoader::class),
            service(BasicCaptcha::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(VerificationHashController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(RobotsController::class)
        ->public()
        ->args([
            service(RobotsPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(StorybookController::class)
        ->public()
        ->args([
            service('twig'),
            service(StorybookService::class),
        ])
        ->call('setContainer', [service('service_container')]);
};
