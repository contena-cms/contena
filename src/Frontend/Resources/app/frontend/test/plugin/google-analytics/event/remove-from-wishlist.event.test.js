import RemoveFromWishlistEvent from 'src/plugin/google-analytics/events/remove-from-wishlist.event';

describe('plugin/google-analytics/events/remove-from-wishlist.event', () => {
    let removeFromWishlistEvent;

    beforeEach(() => {
        window.gtag = jest.fn();

        // Mock PluginManager
        window.PluginManager = {
            getPlugin: jest.fn(() => null),
            initializePluginsInParentElement: jest.fn(),
        };

        removeFromWishlistEvent = new RemoveFromWishlistEvent();
        removeFromWishlistEvent.active = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true', () => {
        expect(removeFromWishlistEvent.supports()).toBe(true);
    });

    test('fires remove_from_wishlist event with blog page data', () => {
        document.body.innerHTML = `
            <h1 class="blog-detail-name">Test Blog</h1>
            <div itemprop="brand"><meta itemprop="name" content="Test Brand"></div>
            <meta property="blog:price:currency" content="EUR">
            <meta property="blog:price:amount" content="99.99">
        `;

        removeFromWishlistEvent._sendEvent('blog-123');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'EUR',
            'value': '99.99',
            'items': [{
                'id': 'blog-123',
                'name': 'Test Blog',
                'brand': 'Test Brand',
            }],
        });
    });

    test('fires remove_from_wishlist event with line item data on checkout pages', () => {
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

        removeFromWishlistEvent._sendEvent('blog-456');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
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

    test('does not fire event on form submit when not active', () => {
        removeFromWishlistEvent.active = false;

        const form = document.createElement('form');
        form.classList.add('blog-wishlist-form');
        form.setAttribute('action', '/wishlist/blog/delete/blog-123');

        removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event on blog removed when not active', () => {
        removeFromWishlistEvent.active = false;

        removeFromWishlistEvent._onProductRemoved({
            detail: { blogId: 'blog-123' },
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event when blogId is missing from event', () => {
        removeFromWishlistEvent._onProductRemoved({
            detail: {},
        });

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('extracts blog ID from form action URL', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/wishlist/blog/delete/abc123-def456');

        const blogId = removeFromWishlistEvent._extractProductIdFromForm(form);

        expect(blogId).toBe('abc123-def456');
    });

    test('returns null when form action does not match pattern', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/some/other/url');

        const blogId = removeFromWishlistEvent._extractProductIdFromForm(form);

        expect(blogId).toBeNull();
    });

    test('fires event with undefined values when no blog data available', () => {
        document.body.innerHTML = '';

        removeFromWishlistEvent._sendEvent('blog-unknown');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
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

        removeFromWishlistEvent._sendEvent('blog-789');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
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

        removeFromWishlistEvent._sendEvent('blog-fallback');

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', {
            'currency': 'USD',
            'value': '25.00',
            'items': [{
                'id': 'blog-fallback',
                'name': 'Fallback Name',
                'brand': 'Fallback Brand',
            }],
        });
    });

    test('fires event on form submit for wishlist form', () => {
        document.body.innerHTML = `
            <h1 class="blog-detail-name">Test Blog</h1>
            <meta property="blog:price:currency" content="EUR">
            <meta property="blog:price:amount" content="99.99">
        `;

        const form = document.createElement('form');
        form.classList.add('blog-wishlist-form');
        form.setAttribute('action', '/wishlist/blog/delete/abc123-def456-789');

        removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_wishlist', expect.objectContaining({
            'items': [{
                'id': 'abc123-def456-789',
                'name': 'Test Blog',
                'brand': undefined,
            }],
        }));
    });

    test('does not fire event on form submit for non-wishlist form', () => {
        const form = document.createElement('form');
        form.classList.add('some-other-form');

        removeFromWishlistEvent._onFormSubmit({ target: form });

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
