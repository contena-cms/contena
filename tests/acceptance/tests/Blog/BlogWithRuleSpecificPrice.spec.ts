import { test, expect, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'User gets a special blog price depending on rules.',
    {
        tag: [
            '@Blog',
            '@Prices',
            '@Rules',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        AdminApiContext,
        ChannelBaseConfig,
        FrontendProductDetail,
        AddProductToCart,
    }) => {
        const blog = await TestDataService.createBasicProduct();
        const rule = await TestDataService.createBasicRule();
        const priceResponse = await AdminApiContext.post('./blog-price', {
            data: {
                blogId: blog.id,
                ruleId: rule.id,
                price: [
                    {
                        currencyId: ChannelBaseConfig.defaultCurrencyId,
                        gross: 8.99,
                        linked: false,
                        net: 7.55,
                    },
                    {
                        currencyId: ChannelBaseConfig.currentCurrencyId,
                        gross: 8.99,
                        linked: false,
                        net: 7.55,
                    },
                ],
                quantityStart: 1,
            },
        });
        expect(priceResponse.ok()).toBeTruthy();

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.expects(FrontendProductDetail.blogSinglePrice).toContainText(formatPrice(10.0));
        await ShopCustomer.attemptsTo(AddProductToCart(blog));
        await ShopCustomer.expects(FrontendProductDetail.offCanvasSummaryTotalPrice).toContainText(formatPrice(8.99));
    },
);
