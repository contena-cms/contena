import { test } from '@fixtures/AcceptanceTest';

test(
    'User is able to verify that wishlist icons disappear when it is disabled.',
    {
        tag: [
            '@Wishlist',
            '@Frontend',
        ],
    },
    async ({
        TestDataService,
        ShopCustomer,
        FrontendHeader,
        FrontendHome,
        FrontendProductDetail,
        AddProductToCart,
        FrontendOffCanvasCart,
    }) => {
        const blog1 = await TestDataService.createBasicProduct();
        const blog1Locators = await FrontendHome.getListingItemByProductName(blog1.name);

        await test.step('Disable wishlist in system settings', async () => {
            await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': false });
        });

        await test.step('Wishlist icon is not displayed in the header', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHeader.wishlistBasket).not.toBeVisible();
        });

        await test.step('Wishlist icon is not displayed on Blog Listings', async () => {
            await ShopCustomer.expects(blog1Locators.wishlistAddedIcon).not.toBeVisible();
            await ShopCustomer.expects(blog1Locators.wishlistNotAddedIcon).not.toBeVisible();
        });

        await test.step('Wishlist icon is not displayed on Blog Detail', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog1));
            await ShopCustomer.expects(FrontendProductDetail.wishlistAddedButton).not.toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.wishlistNotAddedButton).not.toBeVisible();
        });

        await test.step('Wishlist icon is not displayed on Off-Canvas Cart', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(blog1));
            const offcanvasItem = await FrontendOffCanvasCart.getLineItemByProductNumber(blog1.blogNumber);
            await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).not.toBeVisible();
            await ShopCustomer.expects(offcanvasItem.wishlistNotAddedButton).not.toBeVisible();
        });
    },
);
