import Feature from 'src/helper/feature.helper';
import ViewItemEvent from 'src/plugin/google-analytics/events/view-item.event';

describe('plugin/google-analytics/events/view-item.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        Feature.init({ JSON_LD_DATA: false });
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on detail page', () => {
        expect(new ViewItemEvent().supports('', '', 'frontend.detail.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new ViewItemEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(false);
        expect(new ViewItemEvent().supports('', '', 'frontend.home.page')).toBe(false);
    });

    test('fires view_item event with blog data', () => {
        document.body.innerHTML = `
            <div itemtype="https://schema.org/Blog">
                <span itemprop="sku">
                    blog-123
                </span>
                <span itemprop="name">Test Blog</span>
                <div itemprop="brand">
                    <meta itemprop="name" content="Test Brand">
                </div>
            </div>
            <meta property="blog:price:currency" content="EUR">
            <meta property="blog:price:amount" content="99.99">
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Category 1</span>
                <span class="breadcrumb-title">Category 2</span>
            </nav>
        `;

        new ViewItemEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item', {
            'items': [{
                'id': 'blog-123',
                'name': 'Test Blog',
                'brand': 'Test Brand',
                'item_category': 'Category 1',
                'item_category2': 'Category 2',
            }],
            'currency': 'EUR',
            'value': '99.99',
        });
    });

    test('fires view_item event with JSON-LD blog data', () => {
        Feature.init({ JSON_LD_DATA: true });

        const blogData = {
            '@context': 'https://schema.org',
            '@type': 'Blog',
            name: 'JSON-LD Blog',
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

        document.body.innerHTML = `
            <script type="application/ld+json">${JSON.stringify(blogData)}</script>
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Category 1</span>
            </nav>
        `;

        new ViewItemEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item', {
            'items': [{
                'id': 'blog-456',
                'name': 'JSON-LD Blog',
                'brand': 'JSON-LD Brand',
                'item_category': 'Category 1',
            }],
            'currency': 'USD',
            'value': '49.99',
        });
    });

    test('does not fire event when blog itemtype is missing', () => {
        document.body.innerHTML = '<div>No blog here</div>';

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
        new ViewItemEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });

    test('does not fire event when blog ID is missing', () => {
        document.body.innerHTML = `
            <div itemtype="https://schema.org/Blog">
                <span itemprop="name">Test Blog</span>
            </div>
        `;

        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
        new ViewItemEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });
});
