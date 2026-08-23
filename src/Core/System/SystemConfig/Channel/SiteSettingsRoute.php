<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SiteSettingsRoute extends AbstractSiteSettingsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getDecorated(): AbstractSiteSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/site-settings',
        name: 'channel-api.site-settings',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(ChannelContext $context): SiteSettingsRouteResponse
    {
        $channelId = $context->getChannelId();

        $settings = new SiteSettings(
            general: SiteGeneralSettings::fromConfig($this->loadDomain('core.basicInformation', $channelId)),
            loginRegistration: SiteLoginRegistrationSettings::fromConfig($this->loadDomain('core.loginRegistration', $channelId)),
        );

        return new SiteSettingsRouteResponse($settings);
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
