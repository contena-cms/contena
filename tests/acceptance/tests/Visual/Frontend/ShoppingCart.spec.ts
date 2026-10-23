import { test, expect } from '@fixtures/AcceptanceTest';
import { replaceElementsIndividually } from '@contena-ag/acceptance-test-suite';

test(
    'Visual: Frontend Shopping Cart.',
    { tag: '@Visual' },
    async ({
        AddProductToCart,
        ShopCustomer,
        FrontendProductDetail,
        FrontendOffCanvasCart,
        FrontendCheckoutCart,
        TestDataService,
    }) => {
        const blog = await TestDataService.createBasicProduct({
            name: 'TestProduct',
            blogNumber: '123',
        });

        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot of off-canvas shopping cart in frontend.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.attemptsTo(AddProductToCart(blog));
            await ShopCustomer.expects(FrontendOffCanvasCart.goToCartButton).toBeVisible();

            await replaceElementsIndividually(FrontendCheckoutCart.page, [
                {
                    selector: FrontendProductDetail.offCanvasLineItemDeliveryDate,
                    replaceWith: 'Delivery period: 01/01/1970 - 03/01/1970',
                },
            ]);

            await FrontendProductDetail.page.setViewportSize({ width: 1440, height: 800 });

            await expect(FrontendProductDetail.page.locator('.offcanvas')).toHaveScreenshot('OffcanvasCart.png');
        });

        await test.step('Creates a screenshot of shopping cart (checkout/cart) in frontend.', async () => {
            await FrontendOffCanvasCart.goToCartButton.click();
            await ShopCustomer.expects(FrontendCheckoutCart.goToCheckoutButton).toBeVisible();

            await replaceElementsIndividually(FrontendCheckoutCart.page, [
                {
                    selector: FrontendCheckoutCart.blogDeliveryDateLabel,
                    replaceWith: 'Delivery period: 01/01/1970 - 03/01/1970',
                },
            ]);

            await expect(FrontendCheckoutCart.page).toHaveScreenshot('CheckoutCart.png', {
                fullPage: true,
            });
        });
    },
);
