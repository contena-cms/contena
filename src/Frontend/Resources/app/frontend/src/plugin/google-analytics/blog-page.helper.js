import Feature from 'src/helper/feature.helper';

/**
 * Helper for extracting blog data from DOM on blog pages (detail, listing, wishlist)
 * For cart/checkout data, use LineItemHelper instead.
 */
export default class BlogPageHelper {
    /**
     * Gets blog data from available sources (detail page or blog card)
     * @param {string} blogId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for blog card (e.g., form)
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductData(blogId, fallbackElement = null) {
        const detailData = BlogPageHelper.getProductDetailData();

        if (detailData.name) {
            return detailData;
        }

        const cardData = BlogPageHelper.getProductCardData(blogId, fallbackElement);
        return {
            id: cardData.id,
            name: cardData.name,
            brand: cardData.brand,
            currency: detailData.currency,
            value: cardData.value,
        };
    }

    /**
     * Gets blog data from blog detail page
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductDetailData() {
        if (Feature.isActive('JSON_LD_DATA')) {
            const blogData = BlogPageHelper.getJsonLdProductData();

            return {
                id: blogData.sku,
                name: blogData.name,
                brand: blogData.brand,
                currency: blogData.currency || window.currencyIsoCode,
                value: blogData.value,
            };
        }

        return {
            id: BlogPageHelper.getSku(),
            name: document.querySelector('.blog-detail-name')?.textContent.trim(),
            brand: BlogPageHelper.getBrand(),
            currency: BlogPageHelper.getCurrency(),
            value: BlogPageHelper.getValue(),
        };
    }

    /**
     * Gets blog data from blog card (listing page)
     * @param {string} blogId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for blog card
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, value: string|undefined}}
     */
    static getProductCardData(blogId, fallbackElement = null) {
        let blogCard = document.querySelector(`.blog-wishlist-${blogId}`)?.closest('.blog-box');

        // Fallback: find blog card from provided element (e.g., form on wishlist page)
        if (!blogCard && fallbackElement) {
            blogCard = fallbackElement.closest('.blog-box');
        }

        if (!blogCard?.dataset.blogInformation) {
            return {};
        }

        try {
            const info = JSON.parse(blogCard.dataset.blogInformation);
            return {
                id: info.sku ?? blogId,
                name: info.name,
                brand: info.brand,
                value: info.price,
            };
        } catch {
            return {};
        }
    }

    /**
     * Gets SKU from blog detail page
     * @returns {string|undefined}
     */
    static getSku() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return BlogPageHelper.getJsonLdProductData().sku;
        }

        return document.querySelector('[itemprop="sku"]')?.textContent.trim();
    }

    /**
     * Gets brand from blog detail page
     * @returns {string|undefined}
     */
    static getBrand() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return BlogPageHelper.getJsonLdProductData().brand;
        }

        return document.querySelector('[itemprop="brand"] [itemprop="name"]')?.content;
    }

    /**
     * Gets currency from meta tag or global variable
     * @returns {string|undefined}
     */
    static getCurrency() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return BlogPageHelper.getJsonLdProductData().currency || window.currencyIsoCode;
        }

        return document.querySelector('meta[property="blog:price:currency"]')?.content || window.currencyIsoCode;
    }

    /**
     * Gets blog value/price from meta tag
     * @returns {string|undefined}
     */
    static getValue() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return BlogPageHelper.getJsonLdProductData().value;
        }

        return document.querySelector('meta[property="blog:price:amount"]')?.content;
    }

    /**
     * Gets blog data from the JSON-LD blog script
     * @returns {{name: string|undefined, sku: string|undefined, brand: string|undefined, currency: string|undefined, value: string|number|undefined}}
     */
    static getJsonLdProductData() {
        const blogScripts = document.querySelectorAll('script[type="application/ld+json"]');

        for (const blogScript of blogScripts) {
            try {
                const structuredData = JSON.parse(blogScript.textContent);
                const blogData = [structuredData, ...(structuredData['@graph'] ?? [])].find((data) => {
                    const types = Array.isArray(data['@type']) ? data['@type'] : [data['@type']];

                    return types.includes('Blog') || types.includes('BlogGroup');
                });

                if (!blogData) {
                    continue;
                }

                const variant = blogData.hasVariant?.[0];
                const blog = variant ? { ...blogData, ...variant } : blogData;
                const offers = Array.isArray(blog.offers) ? blog.offers[0] : blog.offers;

                return {
                    name: blog.name,
                    sku: blog.sku,
                    brand: blog.brand?.name,
                    currency: offers?.priceCurrency,
                    value: offers?.price ?? offers?.lowPrice,
                };
            } catch {
                continue;
            }
        }

        return {};
    }

    /**
     * Gets category hierarchy from breadcrumbs (GA4 supports up to 5 levels)
     * @returns {Object}
     */
    static getCategories() {
        const breadcrumbNodes = document.querySelectorAll('[aria-label="breadcrumb"] .breadcrumb-title');
        const categories = {};

        breadcrumbNodes.forEach((node, index) => {
            if (index < 5) {
                const key = index === 0 ? 'item_category' : `item_category${index + 1}`;
                categories[key] = node.textContent.trim();
            }
        });

        return categories;
    }
}
