import { test, PropertyGroup, getCurrencyCodeFromLocale, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'As a user, I should see the correct listing price and normal price for variant blogs with differing prices.',
    {
        tag: [
            '@Blog, @Variant',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendHome, FrontendProductDetail, ChannelBaseConfig }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const prices = [
            {
                currencyId: currency.id,
                gross: 10,
                linked: false,
                net: 8.4,
                listPrice: {
                    currencyId: currency.id,
                    gross: 20,
                    linked: false,
                    net: 16.8,
                },
                percentage: {
                    gross: 50,
                    net: 50,
                },
            },
            {
                currencyId: ChannelBaseConfig.defaultCurrencyId,
                gross: 10,
                linked: false,
                net: 8.4,
                listPrice: {
                    currencyId: currency.id,
                    gross: 20,
                    linked: false,
                    net: 16.8,
                },
                percentage: {
                    gross: 50,
                    net: 50,
                },
            },
        ];

        const parentProduct = await TestDataService.createBasicProduct({
            price: prices,
            variantListingConfig: { displayParent: true },
        });
        const propertyGroupColor = await TestDataService.createColorPropertyGroup();
        const propertyGroups: PropertyGroup[] = [];
        propertyGroups.push(propertyGroupColor);
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, propertyGroups, {
            price: prices,
        });

        await TestDataService.clearCaches();

        await test.step('Blog is visible on frontend.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            const blogItemLocators = await FrontendHome.getListingItemByProductName(parentProduct.name);
            await ShopCustomer.expects(blogItemLocators.blogName).toBeVisible();
        });

        await test.step('Validating listing price is available on blog listing page for base variant blog.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            const blogItemLocators = await FrontendHome.getListingItemByProductName(parentProduct.name);
            await ShopCustomer.expects(blogItemLocators.blogPrice).toContainText(formatPrice(10.0));
            await ShopCustomer.expects(blogItemLocators.blogListingPrice).toContainText(formatPrice(20.0));
            await ShopCustomer.expects(blogItemLocators.blogListingPrice).toHaveCSS(
                'text-decoration-line',
                'line-through',
            );
            await ShopCustomer.expects(blogItemLocators.blogListingPricePercentage).toContainText('(50% saved)');
            await ShopCustomer.expects(blogItemLocators.blogListingPriceBadge).toContainText('%');
        });

        await test.step('Validating listing price is available for each variant blog.', async () => {
            for (const variantProduct of variantProducts) {
                await ShopCustomer.goesTo(FrontendProductDetail.url(variantProduct));
                await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toContainText(formatPrice(10.0));
                await ShopCustomer.expects(FrontendProductDetail.blogListingPriceBadge).toContainText('%');
                await ShopCustomer.expects(FrontendProductDetail.blogListingPrice).toContainText(formatPrice(20.0));
                await ShopCustomer.expects(FrontendProductDetail.blogListingPrice).toHaveCSS(
                    'text-decoration-line',
                    'line-through',
                );
                await ShopCustomer.expects(FrontendProductDetail.blogListingPricePercentage).toContainText(
                    '(50% saved)',
                );
            }
        });
    },
);
