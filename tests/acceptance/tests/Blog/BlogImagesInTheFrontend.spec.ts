import { test } from '@fixtures/AcceptanceTest';

test(
    'Shop user should be able to see the blog image in the Frontend.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        FrontendHome,
        FrontendCheckoutCart,
        FrontendCheckoutRegister,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        FrontendSearch,
        FrontendSearchSuggest,
        FrontendAccountOrder,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
        OpenSearchResultPage,
        OpenSearchSuggestPage,
        Login,
        Logout,
        HomeProduct,
        InstanceMeta,
    }) => {
        const blog = HomeProduct;
        const media = await TestDataService.createMediaPNG();

        await TestDataService.assignProductMedia(blog.id, media.id);

        await ShopCustomer.attemptsTo(Login());

        await test.step('Logged-In shop user should be able to see the cover image on the blog listing page.', async () => {
            await ShopCustomer.expects(async () => {
                if (InstanceMeta.isSaaS || InstanceMeta.isPaaS) {
                    await TestDataService.clearCaches();
                }
                await ShopCustomer.goesTo(`${FrontendHome.url()}?a=${Date.now()}`);
                await ShopCustomer.expects(FrontendHome.blogImages.getByAltText(media.alt)).toBeVisible();
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ], // retry after 1 seconds, then every 2.5 seconds
            });
        });

        await test.step('Logged-In shop user should be able to see an image on the blog detail page.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.expects(FrontendProductDetail.blogSingleImage.getByAltText(media.alt)).toBeVisible();
        });

        await test.step('Logged-In shop user should be able to see the cover image in the offcanvas.', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(blog));
            await ShopCustomer.expects(
                FrontendProductDetail.offCanvasLineItemImages.getByAltText(media.alt),
            ).toBeVisible();
        });

        await test.step('Logged-In shop user should be able to see the cover image on the checkout confirm page.', async () => {
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.expects(FrontendCheckoutConfirm.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
        });

        await test.step('Logged-In shop user should be able to see the cover image on the checkout finish page.', async () => {
            await ShopCustomer.attemptsTo(SubmitOrder());
            await ShopCustomer.expects(FrontendCheckoutFinish.cartLineItemImages.getByAltText(media.alt)).toBeVisible();

            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
        });

        await test.step('Logged-In shop user should be able to see the cover image within the account order page.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            // TODO: Migrate to FrontendAccountOrder.orderExpandButton.click(); when https://github.com/contena-cms/acceptance-test-suite/pull/126 is released.
            await ShopCustomer.presses(FrontendAccountOrder.page.locator('.order-hide-btn').first());
            await ShopCustomer.expects(FrontendAccountOrder.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
        });

        await ShopCustomer.attemptsTo(Logout());

        await test.step('Shop user should be able to see the cover image on the checkout register page.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.attemptsTo(AddProductToCart(blog));
            await ShopCustomer.goesTo(FrontendCheckoutRegister.url());
            await ShopCustomer.expects(FrontendCheckoutRegister.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
        });

        await test.step('Shop user should be able to see the cover image on the cart page.', async () => {
            await ShopCustomer.goesTo(FrontendCheckoutCart.url());
            await ShopCustomer.expects(FrontendCheckoutCart.cartLineItemImages.getByAltText(media.alt)).toBeVisible();
        });

        await test.step('Shop user should be able to see the cover image in search suggest page.', async () => {
            await ShopCustomer.attemptsTo(OpenSearchSuggestPage(blog.name));
            await ShopCustomer.expects(
                FrontendSearchSuggest.searchSuggestLineItemImages.getByAltText(media.alt),
            ).toBeVisible();
        });

        await test.step('Shop user should be able to see the cover image on the search result page.', async () => {
            await ShopCustomer.attemptsTo(OpenSearchResultPage(blog.name));
            await ShopCustomer.expects(FrontendSearch.blogImages.getByAltText(media.alt)).toBeVisible();
        });
    },
);
