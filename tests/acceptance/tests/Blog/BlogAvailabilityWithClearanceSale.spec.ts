import { test, expect, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'Blog should be added to the cart if stock:1 and clearance-sale:true.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, AddProductToCart }) => {
        const blog = await TestDataService.createBasicProduct({
            stock: 1,
            isCloseout: true,
        });

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.attemptsTo(AddProductToCart(blog));
    },
);

test(
    'Blog should not be added to the cart if stock:0 and clearance-sale:true.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail }) => {
        const blog = await TestDataService.createBasicProduct({
            stock: 0,
            isCloseout: true,
        });

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.expects(FrontendProductDetail.addToCartButton).toBeHidden();
    },
);

test(
    'Blog should be added to the cart if stock:0 and clearance-sale:false.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, AddProductToCart }) => {
        const blog = await TestDataService.createBasicProduct({
            stock: 0,
            isCloseout: false,
        });

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.attemptsTo(AddProductToCart(blog));
    },
);

test(
    'Blog should be removed from existing cart if stock:0 and cleareance-sale is changed to true.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        AdminApiContext,
        FrontendProductDetail,
        AddProductToCart,
        FrontendCheckoutCart,
    }) => {
        const blog = await TestDataService.createBasicProduct({
            stock: 0,
            isCloseout: false,
        });

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.attemptsTo(AddProductToCart(blog));

        await test.step('Set the clearance sale to true.', async () => {
            const editProductResponse = await AdminApiContext.patch(`blog/${blog.id}`, {
                data: {
                    isCloseout: true,
                },
            });

            expect(editProductResponse.ok()).toBeTruthy();
        });

        await ShopCustomer.goesTo(FrontendCheckoutCart.url());
        await ShopCustomer.expects(FrontendCheckoutCart.emptyCartAlert).toBeVisible();
    },
);

test(
    'Stock reached message should be displayed if stock is changed to 1 and clearance-sale:active after adding 2 blogs to the cart.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        AdminApiContext,
        FrontendProductDetail,
        FrontendCheckoutCart,
        AddProductToCart,
    }) => {
        const blog = await TestDataService.createBasicProduct({
            stock: 2,
            isCloseout: true,
        });

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.attemptsTo(AddProductToCart(blog, '2'));

        await test.step('Set the stock of the blog to 1.', async () => {
            const changeStockResponse = await AdminApiContext.patch(`blog/${blog.id}`, {
                data: {
                    stock: 1,
                },
            });

            expect(changeStockResponse.ok()).toBeTruthy();
        });

        await ShopCustomer.goesTo(FrontendCheckoutCart.url());

        await ShopCustomer.expects(FrontendCheckoutCart.stockReachedAlert).toContainText(blog.name);
        await ShopCustomer.expects(FrontendCheckoutCart.grandTotalPrice).toContainText(formatPrice(10.0));
    },
);
