import { formatPrice, test } from '@fixtures/AcceptanceTest';

test(
    'User gets a special blog price depending on the amount of blogs bought.',
    {
        tag: [
            '@Blog',
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendCheckoutCart,
        FrontendProductDetail,
        FrontendHome,
        AddProductToCart,
        ChangeProductQuantity,
    }) => {
        // 6.8 defaults the cart to the tax column; force the unit-price column so we can assert per-quantity unit prices
        await TestDataService.setSystemConfig({ 'core.cart.columnTaxInsteadUnitPrice': false });
        const blog = await TestDataService.createProductWithPriceRange();
        await test.step('Testing price ranges are available on blog detail page @blog', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('th').nth(0)).toContainText(
                'Quantity',
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('th').nth(1)).toContainText(
                'Unit price',
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('th').nth(2)).toContainText(
                'To 10',
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('td').nth(0)).toContainText(
                formatPrice(100.0),
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('th').nth(3)).toContainText(
                'To 20',
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('td').nth(1)).toContainText(
                formatPrice(90.0),
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('th').nth(4)).toContainText(
                'To 50',
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('td').nth(2)).toContainText(
                formatPrice(80.0),
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('th').nth(5)).toContainText(
                'From 51',
            );
            await ShopCustomer.expects(FrontendProductDetail.blogPriceRangesRow.locator('td').nth(3)).toContainText(
                formatPrice(70.0),
            );
        });

        await test.step('Testing blog with price range (1-10) @blog', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(blog, '10'));
            await ShopCustomer.goesTo(FrontendCheckoutCart.url());
            await ShopCustomer.expects(FrontendCheckoutCart.unitPriceInfo).toContainText(formatPrice(100.0));
        });

        await test.step('Testing blog with price range (11-20) @blog', async () => {
            await ShopCustomer.attemptsTo(ChangeProductQuantity('11'));
            await ShopCustomer.expects(FrontendCheckoutCart.unitPriceInfo).toContainText(formatPrice(90.0));
        });

        await test.step('Testing blog with price range (21-50) @blog', async () => {
            await ShopCustomer.attemptsTo(ChangeProductQuantity('50'));
            await ShopCustomer.expects(FrontendCheckoutCart.unitPriceInfo).toContainText(formatPrice(80.0));
        });

        await test.step('Testing blog last price range (51-infinity) @blog', async () => {
            await ShopCustomer.attemptsTo(ChangeProductQuantity('51'));
            await ShopCustomer.expects(FrontendCheckoutCart.unitPriceInfo).toContainText(formatPrice(70.0));
        });

        await test.step('Testing blog listing contains cheapest price @blog', async () => {
            await ShopCustomer.expects(async () => {
                await ShopCustomer.goesTo(FrontendHome.url());
                await ShopCustomer.expects(
                    FrontendHome.blogListItems.filter({ hasText: blog.name }).locator('.blog-price-wrapper'),
                ).toContainText(formatPrice(70.0));
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ], // retry after 1 seconds, then every 2.5 seconds
            });

            await ShopCustomer.expects(
                FrontendHome.blogListItems.filter({ hasText: blog.name }).getByText('Details'),
            ).toBeVisible();
        });
    },
);
