import Feature from 'src/helper/feature.helper';
import BlogPageHelper from 'src/plugin/google-analytics/blog-page.helper';

describe('plugin/google-analytics/blog-page.helper', () => {
    beforeEach(() => {
        Feature.init({ JSON_LD_DATA: false });
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.currencyIsoCode;
    });

    test('gets SKU, brand, currency and amount value from blog detail markup', () => {
        document.body.innerHTML = `
            <span itemprop="sku">
                blog-123
            </span>
            <div itemprop="brand">
                <meta itemprop="name" content="Test Brand">
            </div>
            <meta property="blog:price:currency" content="EUR">
            <meta property="blog:price:amount" content="99.99">
        `;

        expect(BlogPageHelper.getSku()).toBe('blog-123');
        expect(BlogPageHelper.getBrand()).toBe('Test Brand');
        expect(BlogPageHelper.getCurrency()).toBe('EUR');
        expect(BlogPageHelper.getValue()).toBe('99.99');
    });

    test('gets currency from the global variable when the currency meta tag is missing', () => {
        window.currencyIsoCode = 'USD';

        expect(BlogPageHelper.getCurrency()).toBe('USD');
    });

    test('gets SKU, brand, currency and amount value from JSON-LD blog data', () => {
        Feature.init({ JSON_LD_DATA: true });

        const blogData = {
            '@context': 'https://schema.org',
            '@type': 'Blog',
            name: 'Test Blog',
            sku: 'blog-456',
            brand: {
                '@type': 'Brand',
                name: 'JSON-LD Brand',
            },
            offers: {
                '@type': 'Offer',
                priceCurrency: 'USD',
                price: '49.99',
            },
        };

        document.body.innerHTML = `<script type="application/ld+json">${JSON.stringify(blogData)}</script>`;

        expect(BlogPageHelper.getSku()).toBe('blog-456');
        expect(BlogPageHelper.getBrand()).toBe('JSON-LD Brand');
        expect(BlogPageHelper.getCurrency()).toBe('USD');
        expect(BlogPageHelper.getValue()).toBe('49.99');
    });

    test('gets all variant blog detail data from JSON-LD with one lookup', () => {
        Feature.init({ JSON_LD_DATA: true });
        const blogData = {
            '@type': 'BlogGroup',
            name: 'Blog Group',
            brand: { name: 'Group Brand' },
            hasVariant: [{
                '@type': 'Blog',
                name: 'Variant Blog',
                sku: 'blog-variant',
                offers: { priceCurrency: 'USD', lowPrice: '49.99' },
            }],
        };

        document.body.innerHTML = `<script type="application/ld+json">${JSON.stringify(blogData)}</script>`;
        const getJsonLdProductData = jest.spyOn(BlogPageHelper, 'getJsonLdProductData');

        expect(BlogPageHelper.getProductDetailData()).toEqual({
            id: 'blog-variant',
            name: 'Variant Blog',
            brand: 'Group Brand',
            currency: 'USD',
            value: '49.99',
        });
        expect(getJsonLdProductData).toHaveBeenCalledTimes(1);
    });

    test('gets blog data from the blog JSON-LD script when multiple scripts exist', () => {
        Feature.init({ JSON_LD_DATA: true });

        const breadcrumbData = {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: [],
        };
        const blogData = {
            '@context': 'https://schema.org',
            '@type': 'Blog',
            name: 'Test Blog',
            sku: 'blog-789',
            brand: { name: 'Blog Brand' },
            offers: { priceCurrency: 'EUR', price: '19.99' },
        };

        document.body.innerHTML = `
            <script type="application/ld+json">${JSON.stringify(breadcrumbData)}</script>
            <script type="application/ld+json">${JSON.stringify(blogData)}</script>
        `;

        expect(BlogPageHelper.getSku()).toBe('blog-789');
        expect(BlogPageHelper.getBrand()).toBe('Blog Brand');
        expect(BlogPageHelper.getCurrency()).toBe('EUR');
        expect(BlogPageHelper.getValue()).toBe('19.99');
    });

    test('gets blog data from a blog entity inside JSON-LD graph', () => {
        Feature.init({ JSON_LD_DATA: true });

        const structuredData = {
            '@context': 'https://schema.org',
            '@graph': [
                {
                    '@type': 'BreadcrumbList',
                    itemListElement: [],
                },
                {
                    '@type': 'Blog',
                    name: 'Graph Blog',
                    sku: 'blog-graph',
                    brand: { name: 'Graph Brand' },
                    offers: { priceCurrency: 'GBP', price: '29.99' },
                },
            ],
        };

        document.body.innerHTML = `<script type="application/ld+json">${JSON.stringify(structuredData)}</script>`;

        expect(BlogPageHelper.getSku()).toBe('blog-graph');
        expect(BlogPageHelper.getBrand()).toBe('Graph Brand');
        expect(BlogPageHelper.getCurrency()).toBe('GBP');
        expect(BlogPageHelper.getValue()).toBe('29.99');
    });

    test('gets currency from the global variable when JSON-LD currency is missing', () => {
        Feature.init({ JSON_LD_DATA: true });

        window.currencyIsoCode = 'GBP';
        document.body.innerHTML = '<script type="application/ld+json">{"@type":"Blog"}</script>';

        expect(BlogPageHelper.getCurrency()).toBe('GBP');
    });
});
