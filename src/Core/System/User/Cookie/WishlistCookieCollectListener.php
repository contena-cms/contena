<?php

declare(strict_types=1);

namespace Contena\Core\System\User\Cookie;

use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieEntryCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class WishlistCookieCollectListener
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $channelId = $event->getChannelContext()->getChannelId();
        if (!$this->systemConfigService->getBool('core.cart.wishlistEnabled', $channelId)) {
            return;
        }

        $comfortFeaturesCookieGroup = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        if (!$comfortFeaturesCookieGroup) {
            return;
        }

        $entries = $comfortFeaturesCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $comfortFeaturesCookieGroup->setEntries($entries);
        }

        $entryWishlist = new CookieEntry('wishlist-enabled');
        $entryWishlist->name = 'cookie.groupComfortFeaturesWishlist';
        $entryWishlist->value = '1';
        $entryWishlist->expiration = 30;

        $entries->add($entryWishlist);
    }
}
