import Storage from 'src/helper/storage/storage.helper';
import BaseWishlistStoragePlugin from 'src/plugin/wishlist/base-wishlist-storage.plugin';
import CookieStorageHelper from '../../helper/storage/cookie-storage.helper';

/**
 * @package checkout
 */
export default class WishlistLocalStoragePlugin extends BaseWishlistStoragePlugin {
    init() {
        this.cookieEnabledName = 'wishlist-enabled';

        this.storage = Storage;

        super.init();
        this._registerEvents();
    }

    load() {
        this.blogs = this._fetch();

        super.load();
    }

    /**
     * @deprecated tag:v6.8.0 - The 'router' parameter will be removed.
     */
    // eslint-disable-next-line no-unused-vars
    add(blogId, router = null) {
        this.load();
        super.add(blogId);
        this._save();
    }

    remove(blogId) {
        this.load();
        super.remove(blogId);

        this._save();
    }

    /**
     * @private
     */
    _fetch() {
        if (window.useDefaultCookieConsent && !CookieStorageHelper.getItem(this.cookieEnabledName)) {
            this.storage.removeItem(this._getStorageKey());
        }

        const blogStr = this.storage.getItem(this._getStorageKey());

        if (!blogStr) {
            return {};
        }

        try {
            const blogs = JSON.parse(blogStr);

            return blogs instanceof Object ? blogs : {};
        } catch {
            return {};
        }
    }

    /**
     * @private
     */
    _save() {
        if (this.blogs === null || this.getCurrentCounter() === 0) {
            this.storage.removeItem(this._getStorageKey());
        } else {
            this.storage.setItem(this._getStorageKey(), JSON.stringify(this.blogs));
        }
    }

    /**
     * @private
     */
    _getStorageKey() {
        return 'wishlist-' + (window.channelId || '');
    }

    _registerEvents() {
        const guestLogoutPlugins = window.PluginManager.getPluginInstances('AccountGuestAbortButton');

        if (guestLogoutPlugins) {
            guestLogoutPlugins.forEach(guestLogoutButtonPlugin => {
                guestLogoutButtonPlugin.$emitter.subscribe('guest-logout', () => {
                    this.storage.removeItem(this._getStorageKey());
                });
            });
        }
    }
}
