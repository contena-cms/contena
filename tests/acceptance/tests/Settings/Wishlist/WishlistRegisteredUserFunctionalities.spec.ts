import { test } from '@fixtures/AcceptanceTest';

test(
    'Users can add or remove blogs from their wishlist.',
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
        AddProductToWishlist,
        Login,
        RemoveProductFromWishlist,
        FrontendWishlist,
        AddProductToCartFromWishlist,
        FrontendOffCanvasCart,
        HomeProducts,
    }) => {
        await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

        const [
            blog1,
            blog2,
            blog3,
        ] = HomeProducts;

        const blog1Locators = await FrontendHome.getListingItemByProductName(blog1.name);
        const blog2Locators = await FrontendHome.getListingItemByProductName(blog2.name);
        const blog3Locators = await FrontendHome.getListingItemByProductName(blog3.name);

        await test.step('Add three blogs to the wishlist and verify the basket count updates to 3', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.attemptsTo(AddProductToWishlist(blog1));
            await ShopCustomer.expects(blog1Locators.wishlistAddedIcon).toBeVisible();
            await ShopCustomer.attemptsTo(AddProductToWishlist(blog2));
            await ShopCustomer.expects(blog2Locators.wishlistAddedIcon).toBeVisible();
            await ShopCustomer.attemptsTo(AddProductToWishlist(blog3));
            await ShopCustomer.expects(FrontendHeader.wishlistBasket).toContainText('3');
        });

        await test.step('Remove a blog from the wishlist and verify that the basket updates to 2', async () => {
            await ShopCustomer.attemptsTo(RemoveProductFromWishlist(blog3));
            await ShopCustomer.expects(FrontendHeader.wishlistBasket).toContainText('2');
            await ShopCustomer.expects(blog3Locators.wishlistNotAddedIcon).toBeVisible();
        });

        await test.step('Navigate to the wishlist and verify that the blogs are visible', async () => {
            await ShopCustomer.presses(FrontendHeader.wishlistIcon);
            await ShopCustomer.expects(FrontendHeader.wishlistBasket).toHaveText('2');
            await ShopCustomer.expects(FrontendWishlist.wishListHeader).toBeVisible();
            await ShopCustomer.expects(blog1Locators.blogName).toBeVisible();
            await ShopCustomer.expects(blog2Locators.blogName).toBeVisible();
        });

        await test.step('Remove blog2 from the wishlist page and verify that the basket updates to 1', async () => {
            const listedItemInWishlist = await FrontendWishlist.getListingItemByProductName(blog2.name);
            await ShopCustomer.presses(listedItemInWishlist.removeFromWishlistButton);
            await ShopCustomer.expects(FrontendWishlist.removeAlert).toBeVisible();
            await ShopCustomer.expects(FrontendHeader.wishlistBasket).toContainText('1');
        });

        await test.step('Add blog to cart from wishlist and verify it is added and wishlist icon is visible on offcanvas', async () => {
            const expectedPrice = await blog1Locators.blogPrice.innerText();
            await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(blog1));
            const offcanvasItem = await FrontendOffCanvasCart.getLineItemByProductNumber(blog1.blogNumber);
            const itemPrice = await offcanvasItem.blogTotalPriceValue.innerText();
            await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible();
            await ShopCustomer.expects(itemPrice).toBe(expectedPrice);
        });
    },
);
