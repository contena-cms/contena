<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Extension\ShopSettingsRouteExtension;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ShopSettingsRoute extends AbstractShopSettingsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractShopSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/shop-settings',
        name: 'store-api.shop-settings',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(ChannelContext $context): ShopSettingsRouteResponse
    {
        return $this->extensions->publish(
            name: ShopSettingsRouteExtension::NAME,
            extension: new ShopSettingsRouteExtension($context),
            function: $this->_load(...),
        );
    }

    private function _load(ChannelContext $context): ShopSettingsRouteResponse
    {
        $channelId = $context->getChannelId();

        $basicInformation = $this->loadDomain('core.basicInformation', $channelId);

        $settings = new ShopSettings(
            general: ShopGeneralSettings::fromConfig($basicInformation),
            contactForm: ShopContactFormSettings::fromConfig($basicInformation),
            loginRegistration: ShopLoginRegistrationSettings::fromConfig($this->loadDomain('core.loginRegistration', $channelId)),
            cart: ShopCartSettings::fromConfig($this->loadDomain('core.cart', $channelId)),
            listing: ShopListingSettings::fromConfig($this->loadDomain('core.listing', $channelId)),
            newsletter: ShopNewsletterSettings::fromConfig($this->loadDomain('core.newsletter', $channelId)),
        );

        return new ShopSettingsRouteResponse($settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDomain(string $domain, string $channelId): array
    {
        $config = $this->systemConfigService->get($domain, $channelId);

        return \is_array($config) ? $config : [];
    }
}
