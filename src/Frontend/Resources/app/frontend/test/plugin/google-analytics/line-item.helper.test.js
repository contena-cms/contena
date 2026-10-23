import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

describe('plugin/google-analytics/line-item.helper', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    describe('getLineItems', () => {
        test('returns line items with all data attributes', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                    <span class="hidden-line-item"
                        data-id="blog-123"
                        data-sku="blog-123"
                        data-name="Test Blog"
                        data-quantity="2"
                        data-price="99.99"
                        data-brand="Test Brand">
                    </span>
                </div>
            `;

            const lineItems = LineItemHelper.getLineItems();

            expect(lineItems).toHaveLength(1);
            expect(lineItems[0]).toEqual({
                id: 'blog-123',
                name: 'Test Blog',
                quantity: '2',
                price: '99.99',
                brand: 'Test Brand',
            });
        });

        test('returns line items with categories', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                    <span class="hidden-line-item"
                        data-id="blog-456"
                        data-name="Categorized Blog"
                        data-quantity="1"
                        data-price="49.99"
                        data-brand="Brand"
                        data-category-1="Category 1"
                        data-category-2="Category 2"
                        data-category-3="Category 3">
                    </span>
                </div>
            `;

            const lineItems = LineItemHelper.getLineItems();

            expect(lineItems[0]).toMatchObject({
                item_category: 'Category 1',
                item_category2: 'Category 2',
                item_category3: 'Category 3',
            });
        });

        test('returns multiple line items', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="150.00">
                    <span class="hidden-line-item"
                        data-id="blog-1"
                        data-sku="blog-1"
                        data-name="Blog 1"
                        data-quantity="1"
                        data-price="50.00">
                    </span>
                    <span class="hidden-line-item"
                        data-id="blog-2"
                        data-sku="blog-2"
                        data-name="Blog 2"
                        data-quantity="2"
                        data-price="50.00">
                    </span>
                </div>
            `;

            const lineItems = LineItemHelper.getLineItems();

            expect(lineItems).toHaveLength(2);
            expect(lineItems[0].id).toBe('blog-1');
            expect(lineItems[1].id).toBe('blog-2');
        });
    });

    describe('getAdditionalProperties', () => {
        test('returns currency, shipping, value and tax', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information"
                    data-currency="EUR"
                    data-shipping="4.99"
                    data-value="104.98"
                    data-tax="16.76">
                </div>
            `;

            const props = LineItemHelper.getAdditionalProperties();

            expect(props).toEqual({
                currency: 'EUR',
                shipping: '4.99',
                value: '104.98',
                tax: '16.76',
            });
        });
    });

    describe('getProductData', () => {
        test('returns null when no hidden-line-items-information exists', () => {
            document.body.innerHTML = '';

            const blogData = LineItemHelper.getProductData('blog-123');

            expect(blogData).toBeNull();
        });

        test('returns null when blog is not found in line items', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                    <span class="hidden-line-item"
                        data-id="blog-other"
                        data-name="Other Blog">
                    </span>
                </div>
            `;

            const blogData = LineItemHelper.getProductData('blog-not-found');

            expect(blogData).toBeNull();
        });

        test('returns blog data for matching blog ID', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                    <span class="hidden-line-item"
                        data-id="blog-123"
                        data-sku="blog-123"
                        data-name="Test Blog"
                        data-quantity="2"
                        data-price="99.99"
                        data-brand="Test Brand">
                    </span>
                </div>
            `;

            const blogData = LineItemHelper.getProductData('blog-123');

            expect(blogData).toEqual({
                id: 'blog-123',
                name: 'Test Blog',
                brand: 'Test Brand',
                value: '99.99',
                currency: 'EUR',
                categories: {},
            });
        });

        test('returns blog data with categories', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="USD" data-value="75.00">
                    <span class="hidden-line-item"
                        data-id="blog-456"
                        data-sku="blog-456"
                        data-name="Categorized Blog"
                        data-price="25.00"
                        data-brand="Brand"
                        data-category-1="Electronics"
                        data-category-2="Phones">
                    </span>
                </div>
            `;

            const blogData = LineItemHelper.getProductData('blog-456');

            expect(blogData).toEqual({
                id: 'blog-456',
                name: 'Categorized Blog',
                brand: 'Brand',
                value: '25.00',
                currency: 'USD',
                categories: {
                    item_category: 'Electronics',
                    item_category2: 'Phones',
                },
            });
        });

        test('finds blog among multiple line items', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="150.00">
                    <span class="hidden-line-item"
                        data-id="blog-1"
                        data-sku="blog-1"
                        data-name="First Blog"
                        data-price="50.00">
                    </span>
                    <span class="hidden-line-item"
                        data-id="blog-2"
                        data-sku="blog-2"
                        data-name="Second Blog"
                        data-price="100.00"
                        data-brand="Second Brand">
                    </span>
                </div>
            `;

            const blogData = LineItemHelper.getProductData('blog-2');

            expect(blogData).toEqual({
                id: 'blog-2',
                name: 'Second Blog',
                brand: 'Second Brand',
                value: '100.00',
                currency: 'EUR',
                categories: {},
            });
        });
    });
});
