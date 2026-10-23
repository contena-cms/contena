import Plugin from 'src/plugin-system/plugin.class';
/** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

/**
 * @package checkout
 */
export default class GuestWishlistPagePlugin extends Plugin {
    init() {
        ElementLoadingIndicatorUtil.create(this.el);

        /** @deprecated tag:v6.8.0 - HttpClient is deprecated. Use native fetch API instead. */
        this.httpClient = new HttpClient();
        this._getWishlistStorage();

        this._loadProductListForGuest();
    }

    /**
     * @private
     */
    _getWishlistStorage() {
        const wishlistBasketElement = document.querySelector('#wishlist-basket');

        if (!wishlistBasketElement) {
            return;
        }

        this._wishlistStorage = window.PluginManager.getPluginInstanceFromElement(wishlistBasketElement, 'WishlistStorage');
        this._wishlistStorage.load();
    }

    /**
     * @private
     */
    _loadProductListForGuest() {
        const blogIds = Object.entries(this._wishlistStorage.getProducts())
            .map(([blogId, dateTime]) => ({blogId, dateTime: new Date(dateTime).getTime()}))
            .sort((a, b) => b.dateTime - a.dateTime)
            .map(item => item.blogId);

        fetch(this.options.pageletRouter.path, {
            method: 'POST',
            body: JSON.stringify({ blogIds }),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        })
            .then(response => response.text())
            .then(content => {
                this.el.innerHTML = content;
                const forms = this.el.querySelectorAll('form.blog-wishlist-form');

                if (!forms || forms.length !== blogIds.length) {
                    this._cleanInvalidGuestProductIds(blogIds, forms);
                }

                if (forms && forms.length > 0) {
                    forms.forEach(form => {
                        this._removeGuestProductFormHandler(form);
                    });
                }

                ElementLoadingIndicatorUtil.remove(this.el);
                window.PluginManager.initializePlugins();
            });
    }

    /**
     * @private
     */
    _removeGuestProductFormHandler(form) {
        form.addEventListener('submit', event => {
            event.preventDefault();
            const actionUrlParts = form.getAttribute('action').split('/');
            const blogId = actionUrlParts[actionUrlParts.length - 1];

            if (blogId) {
                const parentEl = form.closest('.cms-listing-col');
                this._wishlistStorage.remove(blogId);
                parentEl.remove();

                if (this._wishlistStorage.getCurrentCounter() === 0) {
                    this._loadProductListForGuest();
                }
            }
        });
    }

    /**
     * @private
     */
    _cleanInvalidGuestProductIds(guestProductIds, forms) {
        const validProductIds = [];

        forms.forEach(form => {
            const actionUrlParts = form.getAttribute('action').split('/');
            const blogId = actionUrlParts[actionUrlParts.length - 1];

            validProductIds.push(blogId);
        });

        guestProductIds.forEach(guestProductId => {
            if (validProductIds.indexOf(guestProductId) === -1) {
                this._wishlistStorage.remove(guestProductId);
            }
        });
    }
}
