import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';
import BlogPageHelper from 'src/plugin/google-analytics/blog-page.helper';

export default class AddToWishlistEvent extends EventAwareAnalyticsEvent
{
    supports() {
        return true;
    }

    getPluginName() {
        return 'WishlistStorage';
    }

    getEvents() {
        return {
            'Wishlist/onProductAdded': this._onProductAdded.bind(this),
        };
    }

    _onProductAdded(event) {
        if (!this.active) {
            return;
        }

        const blogId = event.detail?.blogId;
        if (!blogId) {
            return;
        }

        // Try to get blog data from blog detail/listing page first
        let blogData = BlogPageHelper.getProductData(blogId);
        let categories = BlogPageHelper.getCategories();

        // Fallback to line item data (cart/checkout/finish pages)
        if (!blogData.name) {
            const lineItemData = LineItemHelper.getProductData(blogId);
            if (lineItemData) {
                blogData = lineItemData;
                categories = lineItemData.categories || {};
            }
        }

        gtag('event', 'add_to_wishlist', {
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
