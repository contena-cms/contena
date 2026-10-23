import AddToWishlistEvent from 'src/plugin/google-analytics/events/add-to-wishlist.event';

describe('plugin/google-analytics/events/add-to-wishlist.event', () => {
    let addToWishlistEvent;

    beforeEach(() => {
        window.gtag = jest.fn();

        // Mock PluginManager for EventAwareAnalyticsEvent
        window.PluginManager = {
            getPlugin: jest.fn(() => null),
            initializePluginsInParentElement: jest.fn(),
        };

        addToWishlistEvent = new AddToWishlistEvent();
        addToWishlistEvent.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true', () => {
        expect(addToWishlistEvent.supports()).toBe(true);
    });

    test('getPluginName returns WishlistStorage', () => {
        expect(addToWishlistEvent.getPluginName()).toBe('WishlistStorage');
    });

    test('fires add_to_wishlist event with blog page data', () => {
        document.body.innerHTML = `
            <h1 class="blog-detail-name">Test Blog</h1>
            <div itemprop="brand"><meta itemprop="name" content="Test Brand"></div>
            <meta property="blog:price:currency" content="EUR">
            <meta property="blog:price:amount" content="99.99">
        `;

        addToWishlistEvent._onProductAdded({
            detail: { blogId: 'blog-123' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
            'currency': 'EUR',
            'value': '99.99',
            'items': [{
                'id': 'blog-123',
                'name': 'Test Blog',
                'brand': 'Test Brand',
            }],
        });
    });

    test('fires add_to_wishlist event with line item data on checkout pages', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="blog-456"
                    data-name="Line Item Blog"
                    data-quantity="1"
                    data-price="49.99"
                    data-brand="Line Item Brand"
                    data-category-1="Category 1"
                    data-category-2="Category 2">
                </span>
            </div>
        `;

        addToWishlistEvent._onProductAdded({
            detail: { blogId: 'blog-456' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
            'currency': 'EUR',
            'value': '49.99',
            'items': [{
                'id': 'blog-456',
                'name': 'Line Item Blog',
                'brand': 'Line Item Brand',
                'item_category': 'Category 1',
                'item_category2': 'Category 2',
            }],
        });
    });

    test('does not fire event when not active', () => {
        addToWishlistEvent.active = false;

        addToWishlistEvent._onProductAdded({
            detail: { blogId: 'blog-123' },
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event when blogId is missing', () => {
        addToWishlistEvent._onProductAdded({
            detail: {},
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('fires event with undefined values when no blog data available', () => {
        document.body.innerHTML = '';

        addToWishlistEvent._onProductAdded({
            detail: { blogId: 'blog-unknown' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
            'currency': undefined,
            'value': undefined,
            'items': [{
                'id': 'blog-unknown',
                'name': undefined,
                'brand': undefined,
            }],
        });
    });

    test('prefers blog page data over line item data', () => {
        document.body.innerHTML = `
            <h1 class="blog-detail-name">Blog Page Name</h1>
            <div itemprop="brand"><meta itemprop="name" content="Blog Page Brand"></div>
            <meta property="blog:price:currency" content="EUR">
            <meta property="blog:price:amount" content="79.99">
            <div class="hidden-line-items-information" data-currency="USD" data-value="100.00">
                <span class="hidden-line-item"
                    data-id="blog-789"
                    data-name="Line Item Name"
                    data-price="50.00"
                    data-brand="Line Item Brand">
                </span>
            </div>
        `;

        addToWishlistEvent._onProductAdded({
            detail: { blogId: 'blog-789' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
            'currency': 'EUR',
            'value': '79.99',
            'items': [{
                'id': 'blog-789',
                'name': 'Blog Page Name',
                'brand': 'Blog Page Brand',
            }],
        });
    });

    test('falls back to line item data when blog page data has no name', () => {
        document.body.innerHTML = `
            <meta property="blog:price:currency" content="EUR">
            <div class="hidden-line-items-information" data-currency="USD" data-value="100.00">
                <span class="hidden-line-item"
                    data-id="blog-fallback"
                    data-name="Fallback Name"
                    data-price="25.00"
                    data-brand="Fallback Brand">
                </span>
            </div>
        `;

        addToWishlistEvent._onProductAdded({
            detail: { blogId: 'blog-fallback' },
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_wishlist', {
            'currency': 'USD',
            'value': '25.00',
            'items': [{
                'id': 'blog-fallback',
                'name': 'Fallback Name',
                'brand': 'Fallback Brand',
            }],
        });
    });
});
