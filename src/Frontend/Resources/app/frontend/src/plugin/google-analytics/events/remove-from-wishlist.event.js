import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';
import BlogPageHelper from 'src/plugin/google-analytics/blog-page.helper';

export default class RemoveFromWishlistEvent extends AnalyticsEvent
{
    supports() {
        return true;
    }

    execute() {
        this._registerWishlistStorageEvents();
        document.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    _registerWishlistStorageEvents() {
        let instances;
        try {
            instances = window.PluginManager.getPluginInstances('WishlistStorage');
        } catch {
            return; // Optional plugin
        }

        if (!instances?.length) {
            return;
        }

        instances.forEach((pluginInstance) => {
            pluginInstance.$emitter.subscribe('Wishlist/onProductRemoved', this._onProductRemoved.bind(this));
        });
    }

    _onFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const form = event.target;
        if (!form.classList.contains('blog-wishlist-form')) {
            return;
        }

        const blogId = this._extractProductIdFromForm(form);
        if (!blogId) {
            return;
        }

        this._sendEvent(blogId, form);
    }

    _onProductRemoved(event) {
        if (!this.active) {
            return;
        }

        const blogId = event.detail?.blogId;
        if (!blogId) {
            return;
        }

        this._sendEvent(blogId);
    }

    /**
     * Extracts blog ID from wishlist form action URL
     * @param {HTMLFormElement} form
     * @returns {string|null}
     * @private
     */
    _extractProductIdFromForm(form) {
        const actionUrl = form.getAttribute('action');
        const match = actionUrl.match(/\/wishlist\/blog\/delete\/([a-f0-9-]+)/i);
        return match ? match[1] : null;
    }

    /**
     * Sends the remove_from_wishlist event
     * @param {string} blogId
     * @param {HTMLFormElement|null} form
     * @private
     */
    _sendEvent(blogId, form = null) {
        // Try to get blog data from blog detail/listing page first
        let blogData = BlogPageHelper.getProductData(blogId, form);
        let categories = BlogPageHelper.getCategories();

        // Fallback to line item data (cart/checkout/finish pages)
        const lineItemData = LineItemHelper.getProductData(blogId);
        if (!blogData.name && lineItemData) {
            blogData = lineItemData;
            categories = lineItemData.categories || {};
        }

        gtag('event', 'remove_from_wishlist', {
            'currency': blogData.currency,
            'value': blogData.value,
            'items': [{
                'id': blogData.id ?? blogId,
                'name': blogData.name,
                'brand': blogData.brand,
                ...categories,
            }],
        });
    }
}

