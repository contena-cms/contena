import { test } from '@fixtures/AcceptanceTest';

let originalConfig: Record<string, unknown> = {};

test.describe('Wishlist Guest Functionalities', () => {
    test.beforeEach(async ({ TestDataService }) => {
        // Get and store current config
        const getCurrentConfig = await TestDataService.AdminApiClient.get('_action/system-config?domain=core');
        if (!getCurrentConfig.ok()) {
            throw new Error(`Failed to get system config: ${getCurrentConfig.status()} ${getCurrentConfig.statusText()}`);
        }
        originalConfig = await getCurrentConfig.json();

        const updatedConfig = {
            null: {
                ...(originalConfig as Record<string, unknown>),
                'core.cart.wishlistEnabled': true,
                'core.basicInformation.acceptAllCookies': true,
            },
        };

        const updateResponse = await TestDataService.AdminApiClient.post('_action/system-config/batch', {
            data: updatedConfig,
        });
        if (!updateResponse.ok()) {
            throw new Error(`Failed to update system config: ${updateResponse.status()} ${updateResponse.statusText()}`);
        }

        await TestDataService.clearCaches();
    });

    test.afterEach(async ({ TestDataService }) => {
        if (Object.keys(originalConfig).length > 0) {
            await TestDataService.AdminApiClient.post('_action/system-config/batch', {
                data: { null: originalConfig },
            });

            await TestDataService.clearCaches();
        }
    });

    test(
        'Guest user is able to add and remove blogs to the wishlist',
        {
            tag: [
                '@Wishlist',
                '@Frontend',
            ],
        },
        async ({
            ShopCustomer,
            FrontendHeader,
            FrontendHome,
            AddProductToWishlist,
            FrontendWishlist,
            AddProductToCartFromWishlist,
            Login,
            FrontendOffCanvasCart,
            HomeProducts,
        }) => {
            const [
                blog1,
                blog2,
            ] = HomeProducts;

            await test.step('Navigate to home and accept cookies', async () => {
                await ShopCustomer.goesTo(FrontendHome.url());
                await ShopCustomer.presses(FrontendHome.consentAcceptAllCookiesButton);

                // Wait for banner to disappear
                await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible();

                // Wait for cookies to be set (critical for wishlist functionality)
                await ShopCustomer.expects(async () => {
                    const cookies = await FrontendHome.page.context().cookies();
                    const cookiePreference = cookies.find((c) => c.name === 'cookie-preference')?.value;
                    const wishlistCookie = cookies.find((c) => c.name === 'wishlist-enabled')?.value;
                    await ShopCustomer.expects(cookiePreference).toBe('1');
                    await ShopCustomer.expects(wishlistCookie).toBe('1');
                }).toPass({
                    intervals: [
                        1_000,
                        2_500,
                    ],
                });
            });

            const blog1Locators = await FrontendHome.getListingItemByProductName(blog1.name);
            const blog2Locators = await FrontendHome.getListingItemByProductName(blog2.name);

            await test.step('Add blog1 to the wishlist and verify wishlist count updates to 1', async () => {
                await ShopCustomer.attemptsTo(AddProductToWishlist(blog1));
                await ShopCustomer.expects(blog1Locators.wishlistAddedIcon).toBeVisible();
                await ShopCustomer.expects(FrontendHeader.wishlistBasket).toHaveText('1');
            });

            await test.step('Add blog1 to the cart from wishlist and verify cart total is same with blog price', async () => {
                const blogPrice = await blog1Locators.blogPrice.innerText();
                await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(blog1));
                const offCanvasSubtotal = await FrontendOffCanvasCart.subTotalPrice.innerText();
                ShopCustomer.expects(offCanvasSubtotal).toBe(blogPrice);
                const offcanvasItem = await FrontendOffCanvasCart.getLineItemByProductNumber(blog1.blogNumber);
                await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible();
            });

            await test.step('Login as user and verify blog1 is still in wishlist', async () => {
                await ShopCustomer.attemptsTo(Login());
                await ShopCustomer.expects(FrontendHeader.wishlistBasket).toHaveText('1', { timeout: 15_000 });
                await ShopCustomer.goesTo(FrontendHome.url());
                await ShopCustomer.expects(blog1Locators.wishlistAddedIcon).toBeVisible({ timeout: 15_000 });
            });

            await test.step('Add blog2 to the wishlist and verify', async () => {
                await ShopCustomer.attemptsTo(AddProductToWishlist(blog2));

                await ShopCustomer.expects(blog2Locators.wishlistAddedIcon).toBeVisible({ timeout: 15_000 });
            });

            await test.step('Navigate to the wishlist and verify that the blogs are visible', async () => {
                await ShopCustomer.presses(FrontendHeader.wishlistIcon);
                await ShopCustomer.expects(FrontendHeader.wishlistBasket).toHaveText('2', { timeout: 15_000 });
                await ShopCustomer.expects(FrontendWishlist.wishListHeader.first()).toBeVisible();
                await ShopCustomer.expects(blog1Locators.blogName).toBeVisible();
                await ShopCustomer.expects(blog2Locators.blogName).toBeVisible();
            });
        },
    );
});
