import { test, expect } from '@fixtures/AcceptanceTest';
import { hideElements, replaceElements } from '@contena-ag/acceptance-test-suite';

test(
    'Visual: frontend:checkout/finish.',
    { tag: '@Visual' },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
        FrontendCheckoutFinish,
        FrontendCheckoutConfirm,
        FrontendCheckoutRegister,
    }) => {
        const blog = await TestDataService.createBasicProduct();

        await test.step('Create screenshot of checkout/register page in frontend.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.attemptsTo(AddProductToCart(blog));
            await FrontendProductDetail.offCanvasCartGoToCheckoutButton.click();
            await ShopCustomer.expects(FrontendCheckoutRegister.cartLineItemImages).toBeVisible();

            await hideElements(FrontendCheckoutRegister.page, ['.cookie-permission-container']);
            await replaceElements(FrontendCheckoutRegister.page, [
                '.line-item-label',
                '.line-item-blog-number',
                '.line-item-delivery-date',
            ]);

            await expect(FrontendCheckoutRegister.page).toHaveScreenshot('Checkout-Register.png', {
                fullPage: true,
            });
        });

        await test.step('Create screenshot of checkout/confirm page in frontend.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.attemptsTo(AddProductToCart(blog));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

            await hideElements(FrontendCheckoutConfirm.page, ['.cookie-permission-container']);

            await replaceElements(FrontendCheckoutConfirm.page, [
                '.line-item-label',
                '.line-item-blog-number',
                '.line-item-delivery-date',
                '.confirm-address-shipping',
                '.confirm-address-billing',
            ]);

            await expect(FrontendCheckoutConfirm.page).toHaveScreenshot('Checkout-Confirm.png', {
                fullPage: true,
            });
        });

        await test.step('Create screenshot of checkout/finish page in frontend.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));
            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            await FrontendCheckoutFinish.page.setViewportSize({ width: 1280, height: 1440 });

            await hideElements(FrontendCheckoutFinish.page, ['.cookie-permission-container']);
            await replaceElements(FrontendCheckoutFinish.page, [
                '.line-item-label',
                '.line-item-blog-number',
                '.line-item-delivery-date',
                '.finish-ordernumber',
                '.finish-address-shipping',
                '.finish-address-billing',
            ]);

            await expect(FrontendCheckoutFinish.page).toHaveScreenshot('Checkout-Finish.png', {
                fullPage: true,
            });
        });
    },
);
