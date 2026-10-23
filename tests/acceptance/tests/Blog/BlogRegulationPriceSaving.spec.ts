import { test, getCurrencyCodeFromLocale, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'As a user, I should see savings calculated against the lowest price of the last 30 days instead of the list price.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendHome, FrontendProductDetail, ChannelBaseConfig }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const getPrices = (unitPrice: number) =>
            [currency.id, ChannelBaseConfig.defaultCurrencyId].map((currencyId) => ({
                currencyId,
                gross: unitPrice,
                linked: false,
                net: unitPrice,
                listPrice: { currencyId, gross: 100, linked: false, net: 100 },
                regulationPrice: { currencyId, gross: 80, linked: false, net: 80 },
            }));

        // 1 - 75 / 80 = 6.25% saved against the lowest price of the last 30 days, the list price of 100 is not the reference.
        const reducedProduct = await TestDataService.createBasicProduct({ price: getPrices(75) });
        // Above the lowest price of the last 30 days there is no saving to announce, although the list price is higher.
        const raisedProduct = await TestDataService.createBasicProduct({ price: getPrices(85) });

        await TestDataService.clearCaches();

        await test.step('The blog detail page shows the saving against the lowest price of the last 30 days.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(reducedProduct));
            await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toContainText(formatPrice(75.0));
            await ShopCustomer.expects(FrontendProductDetail.blogListingPrice).not.toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.page.locator('.regulation-price')).toContainText(
                `Lowest price (last 30 days): ${formatPrice(80.0)}`,
            );
            await ShopCustomer.expects(FrontendProductDetail.blogListingPricePercentage).toContainText('(6.25% saved)');
            await ShopCustomer.expects(FrontendProductDetail.blogListingPriceBadge).toBeVisible();
        });

        await test.step('The blog detail page announces no saving above the lowest price of the last 30 days.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(raisedProduct));
            await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toContainText(formatPrice(85.0));
            await ShopCustomer.expects(FrontendProductDetail.blogListingPrice).not.toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.page.locator('.regulation-price')).toContainText(
                `Lowest price (last 30 days): ${formatPrice(80.0)}`,
            );
            await ShopCustomer.expects(FrontendProductDetail.blogListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.blogListingPriceBadge).not.toBeVisible();
        });

        await test.step('The listing shows a discount only for the saving against the lowest price of the last 30 days.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());

            const reducedItem = await FrontendHome.getListingItemByProductName(reducedProduct.name);
            await ShopCustomer.expects(reducedItem.blogListingPrice).not.toBeVisible();
            await ShopCustomer.expects(reducedItem.blogListingPricePercentage).toContainText('(6.25% saved)');
            await ShopCustomer.expects(reducedItem.blogListingPriceBadge).toBeVisible();

            const raisedItem = await FrontendHome.getListingItemByProductName(raisedProduct.name);
            await ShopCustomer.expects(raisedItem.blogListingPrice).not.toBeVisible();
            await ShopCustomer.expects(raisedItem.blogListingPricePercentage).not.toBeVisible();
            await ShopCustomer.expects(raisedItem.blogListingPriceBadge).not.toBeVisible();
        });
    },
);
