import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import BlogPageHelper from 'src/plugin/google-analytics/blog-page.helper';

export default class ViewItemListEvent extends EventAwareAnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports() {
        const listingWrapper = document.querySelector('.cms-element-blog-listing-wrapper');
        return !!listingWrapper;
    }

    getPluginName() {
        return 'Listing';
    }

    getEvents() {
        return {
            'Listing/afterRenderResponse': this._onListingChange.bind(this),
        };
    }

    execute() {
        // Fire on initial page load
        this._fireViewItemListEvent();

        // Subscribe to listing updates (pagination, filters)
        super.execute();
    }

    _onListingChange() {
        this._fireViewItemListEvent();
    }

    _fireViewItemListEvent() {
        if (!this.active) {
            return;
        }

        const items = this.getListItems();
        if (items.length === 0) {
            return;
        }

        // Calculate total value of all visible items
        const value = items.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0);

        gtag('event', 'view_item_list', {
            'currency': BlogPageHelper.getCurrency(),
            'value': value.toFixed(2),
            'items': items,
        });
    }

    getListItems() {
        const blogBoxes = document.querySelectorAll('.blog-box');
        const lineItems = [];

        if (!blogBoxes) {
            return lineItems;
        }

        // Get category from breadcrumbs (same for all items on this page)
        const categories = BlogPageHelper.getCategories();

        blogBoxes.forEach(item => {
            if (item.dataset.blogInformation) {
                const blogData = JSON.parse(item.dataset.blogInformation);
                const { sku, id, ...properties } = blogData;
                lineItems.push({
                    ...properties,
                    id: sku ?? id,
                    ...categories,
                });
            }
        });

        return lineItems;
    }
}
