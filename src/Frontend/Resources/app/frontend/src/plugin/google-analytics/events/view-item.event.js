import Feature from 'src/helper/feature.helper';
import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';
import BlogPageHelper from 'src/plugin/google-analytics/blog-page.helper';

export default class ViewItemEvent extends AnalyticsEvent
{
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return activeRoute === 'frontend.detail.page';
    }

    execute() {
        if (!this.active) {
            return;
        }

        const blogData = BlogPageHelper.getProductDetailData();
        let blogId = blogData.id;
        let blogName = blogData.name;

        if (!Feature.isActive('JSON_LD_DATA')) {
            const blogItemElement = document.querySelector('[itemtype="https://schema.org/Blog"]');
            if (!blogItemElement) {
                console.warn('[Google Analytics Plugin] Blog itemtype ([itemtype="https://schema.org/Blog"]) could not be found in document.');
                return;
            }

            const blogIdElement = blogItemElement.querySelector('[itemprop="sku"]');
            const blogNameElement = blogItemElement.querySelector('[itemprop="name"]');
            if (!blogIdElement || !blogNameElement) {
                console.warn('[Google Analytics Plugin] Blog ID ([itemprop="sku"]) or blog name ([itemprop="name"]) could not be found within blog scope.');
                return;
            }

            blogId = blogIdElement.textContent.trim();
            blogName = blogNameElement.textContent.trim();
        }

        if (!blogId || !blogName) {
            console.warn('[Google Analytics Plugin] Blog ID or blog name is empty, do not track page view.');
            return;
        }

        gtag('event', 'view_item', {
            'currency': blogData.currency,
            'value': blogData.value,
            'items': [{
                'id': blogId,
                'name': blogName,
                'brand': blogData.brand,
                ...BlogPageHelper.getCategories(),
            }],
        });
    }
}
