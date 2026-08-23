<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\HreflangLoaderParameter;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class TemplateDataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly HreflangLoaderInterface $hreflangLoader,
        private readonly ThemeRuntimeConfigService $runtimeConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FrontendRenderEvent::class => [
                ['addHreflang'],
                ['addIconSetConfig'],
            ],
        ];
    }

    public function addHreflang(FrontendRenderEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->getBoolean('_esi')) {
            return;
        }

        $route = $request->attributes->get('_route');
        if ($route === null) {
            return;
        }

        $routeParams = $request->attributes->get('_route_params', []);

        $channelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        $parameter = new HreflangLoaderParameter($route, $routeParams, $channelContext, $route === 'frontend.home.page', $request->getBasePath());
        $event->setParameter('hrefLang', $this->hreflangLoader->load($parameter));
    }

    public function addIconSetConfig(FrontendRenderEvent $event): void
    {
        $request = $event->getRequest();

        // get name if theme is not inherited
        $theme = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_NAME);
        if (!$theme) {
            // get theme name from base theme because for inherited themes the name is always null
            $theme = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        }

        if (!$theme) {
            return;
        }

        $runtimeConfig = $this->runtimeConfigService->getRuntimeConfigByName($theme);
        if (!$runtimeConfig) {
            return;
        }

        $event->setParameter('themeIconConfig', $runtimeConfig->iconSets);
    }
}
