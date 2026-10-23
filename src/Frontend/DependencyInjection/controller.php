<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\LineItemFactoryRegistry;
use Contena\Core\Checkout\Cart\Channel\CartLoadRoute;
use Contena\Core\Checkout\Cart\Channel\CartReorderRoute;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\AddWishlistBlogRoute;
use Contena\Core\System\User\Channel\ChangeUserProfileRoute;
use Contena\Core\System\User\Channel\ChangeEmailRoute;
use Contena\Core\System\User\Channel\ChangePasswordRoute;
use Contena\Core\System\User\Channel\ConvertGuestRoute;
use Contena\Core\System\User\Channel\DeleteAddressRoute;
use Contena\Core\System\User\Channel\DeleteUserRoute;
use Contena\Core\System\User\Channel\DownloadRoute;
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
use Contena\Core\System\User\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\System\User\Channel\UpsertAddressRoute;
use Contena\Core\Checkout\DocumentV2\Channel\DocumentRoute;
use Contena\Core\Checkout\Order\Channel\CancelOrderRoute;
use Contena\Core\Checkout\Order\Channel\OrderRoute;
use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Checkout\Order\Channel\SetPaymentOrderRoute;
use Contena\Core\Checkout\Payment\PaymentProcessor;
use Contena\Core\Checkout\Payment\Channel\HandlePaymentMethodRoute;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\Cms\Channel\CmsRoute;
use Contena\Core\Content\ContactForm\Channel\ContactFormRoute;
use Contena\Core\Content\Cookie\Channel\CookieRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterConfirmRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterUnsubscribeRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\Garan\GaranLabelRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\BlogListRoute;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewSaveRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\RevocationRequest\Channel\RevocationRequestRoute;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Sitemap\Channel\SitemapFileRoute;
use Contena\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Contena\Core\Framework\App\Api\AppJWTGenerateRoute;
use Contena\Core\Framework\Gateway\Context\Channel\ContextGatewayRoute;
use Contena\Core\Framework\Script\Api\ScriptResponseEncoder;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Controller\AccountOrderController;
use Contena\Frontend\Controller\AccountProfileController;
use Contena\Frontend\Controller\AddressController;
use Contena\Frontend\Controller\Api\CaptchaController as ApiCaptchaController;
use Contena\Frontend\Controller\AppController;
use Contena\Frontend\Controller\AuthController;
use Contena\Frontend\Controller\CaptchaController;
use Contena\Frontend\Controller\CartLineItemController;
use Contena\Frontend\Controller\CheckoutController;
use Contena\Frontend\Controller\CmsController;
use Contena\Frontend\Controller\ContextController;
use Contena\Frontend\Controller\ContextGatewayController;
use Contena\Frontend\Controller\CookieController;
use Contena\Frontend\Controller\CountryStateController;
use Contena\Frontend\Controller\DocumentController;
use Contena\Frontend\Controller\DownloadController;
use Contena\Frontend\Controller\ErrorController;
use Contena\Frontend\Controller\FormController;
use Contena\Frontend\Controller\LandingPageController;
use Contena\Frontend\Controller\MaintenanceController;
use Contena\Frontend\Controller\NavigationController;
use Contena\Frontend\Controller\NewsletterController;
use Contena\Frontend\Controller\BlogController;
use Contena\Frontend\Controller\RegisterController;
use Contena\Frontend\Controller\RobotsController;
use Contena\Frontend\Controller\ScriptController;
use Contena\Frontend\Controller\SearchController;
use Contena\Frontend\Controller\SitemapController;
use Contena\Frontend\Controller\StorybookController;
use Contena\Frontend\Controller\VerificationHashController;
use Contena\Frontend\Controller\WellKnownController;
use Contena\Frontend\Controller\WishlistController;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Framework\Twig\ErrorTemplateResolver;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoader;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\Order\AccountEditOrderPageLoader;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoader;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoader;
use Contena\Frontend\Page\Account\Overview\AccountOverviewPageLoader;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoader;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPageLoader;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Contena\Frontend\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Contena\Frontend\Page\Navigation\Error\ErrorPageLoader;
use Contena\Frontend\Page\Navigation\NavigationPageLoader;
use Contena\Frontend\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageLoader;
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
            tagged_iterator('contena.frontend.captcha'),
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
            service(ChannelContextService::class),
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
            service(ChangeUserProfileRoute::class),
            service(ChangePasswordRoute::class),
            service(ChangeEmailRoute::class),
            service(DeleteUserRoute::class),
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
            service(ChannelContextService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(ResetPasswordRoute::class),
            service(LoginRoute::class),
            service(LogoutRoute::class),
            service(ImitateUserRoute::class),
            service(FrontendCartFacade::class),
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
            service(BlogLineItemFactory::class),
            service(HtmlSanitizer::class),
            service(BlogListRoute::class),
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
            service(BlogListingRoute::class),
            service(BlogDetailRoute::class),
            service(BlogReviewLoader::class),
            service(FindBlogVariantRoute::class),
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

    $services->set(BlogController::class)
        ->args([
            service(BlogPageLoader::class),
            service(FindBlogVariantRoute::class),
            service(MinimalQuickViewPageLoader::class),
            service(BlogReviewSaveRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(BlogReviewLoader::class),
            service(BlogPurchaseLimitRoute::class),
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
            service('user.repository'),
            service(UserGroupRegistrationPageLoader::class),
            service('channel_domain.repository'),
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
            service(BlogSearchRoute::class),
            param('contena.frontend.redirect_on_single_hit_fields'),
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
            service(AddWishlistBlogRoute::class),
            service(RemoveWishlistBlogRoute::class),
            service(MergeWishlistBlogRoute::class),
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
