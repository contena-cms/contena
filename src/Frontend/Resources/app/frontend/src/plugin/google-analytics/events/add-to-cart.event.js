import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import BlogPageHelper from 'src/plugin/google-analytics/blog-page.helper';

export default class AddToCartEvent extends EventAwareAnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    getPluginName() {
        return 'AddToCart';
    }

    getEvents() {
        return {
            'beforeFormSubmit':  this._beforeFormSubmit.bind(this),
        };
    }

    _beforeFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const formData = event.detail;
        const formElement = event.target;
        let blogId = null;
        let isLineItemForm = false;

        formData.forEach((value, key) => {
            if (key.startsWith('lineItems[')) {
                isLineItemForm = true;
            }

            if (key.endsWith('[id]')) {
                blogId = value;
            }
        });

        // reorder posts no line items, the route resolves them from the order, so there is nothing to report here
        if (!isLineItemForm) {
            return;
        }

        if (!blogId) {
            console.warn('[Google Analytics Plugin] Blog ID could not be fetched. Skipping.');
            return;
        }

        // Get blog data - uses detail page meta tags or falls back to blog card data
        const blogData = BlogPageHelper.getProductData(blogId, formElement);

        gtag('event', 'add_to_cart', {
            'currency': blogData.currency || BlogPageHelper.getCurrency(),
            'value': blogData.value,
            'items': [{
                'id': blogData.id ?? blogId,
                'name': formData.get('blog-name') || blogData.name,
                'quantity': formData.get(`lineItems[${blogId}][quantity]`),
                'brand': formData.get('brand-name') || blogData.brand,
                ...BlogPageHelper.getCategories(),
            }],
        });
    }
}
