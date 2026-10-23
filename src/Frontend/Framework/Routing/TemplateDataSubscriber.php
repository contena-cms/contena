<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Content\Seo\HreflangLoaderParameter;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\Blog\BlogPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Contena\Core\Framework\App\Exception\ShopIdChangeSuggestedException;

/**
 * @internal
 */
class TemplateDataSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            FrontendRenderEvent::class => [
                ['addHreflang'],
                ['addShopIdParameter'],
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

        // When we have a blog page, we should make sure that the hreflang matches the canonical URL, relevant if we have a dedicated canonical URL or if we are on the parent blog page
        if ($route === BlogPageSeoUrlRoute::ROUTE_NAME) {
            $page = $event->getParameter('page');
            if ($page instanceof BlogPage) {
                $routeParams['blogId'] = $page->getBlog()->getCanonicalBlogId() ?? $page->getBlog()->getId();
            }
        }

        $channelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        $parameter = new HreflangLoaderParameter($route, $routeParams, $channelContext, $route === 'frontend.home.page', $request->getBasePath());
        $event->setParameter('hrefLang', $this->hreflangLoader->load($parameter));
    }

    public function addShopIdParameter(FrontendRenderEvent $event): void
    {
        if (!$this->activeAppsLoader->getActiveApps()) {
            return;
        }

        try {
            $shopId = $this->shopIdProvider->getShopId()->id;
        } catch (ShopIdChangeSuggestedException) {
            return;
        }

        $event->setParameter('appShopId', $shopId);
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
