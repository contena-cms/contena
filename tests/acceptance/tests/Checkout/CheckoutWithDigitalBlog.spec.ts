import { test, expect, getOrderTransactionId } from '@fixtures/AcceptanceTest';

test(
    'Registered shop user should be able to buy a digital blog.',
    {
        tag: [
            '@Checkout',
            '@DigitalProduct',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        AdminApiContext,
        TestDataService,
        FrontendProductDetail,
        FrontendCheckoutFinish,
        FrontendAccountOrder,
        Login,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        ConfirmImmediateAccessToDigitalProduct,
        SelectPaymentMethod,
        SubmitOrder,
        DownloadDigitalProductFromOrderAndExpectContentToBe,
    }) => {
        const fileContent = 'This is a test.';
        const digitalProduct = await TestDataService.createDigitalProduct(fileContent);

        await ShopCustomer.attemptsTo(Login());

        await ShopCustomer.goesTo(FrontendProductDetail.url(digitalProduct));

        await ShopCustomer.presses(FrontendProductDetail.addToCartButton);
        await ShopCustomer.expects(FrontendProductDetail.offCanvasCartTitle).toBeVisible();
        await ShopCustomer.expects(FrontendProductDetail.offCanvasCart.getByText(digitalProduct.name)).toBeVisible();
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(ConfirmImmediateAccessToDigitalProduct());
        await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));

        await ShopCustomer.attemptsTo(SubmitOrder());

        const orderId = FrontendCheckoutFinish.getOrderId();

        TestDataService.addCreatedRecord('order', orderId);

        await test.step('Set the order to "paid", so the user can access the file.', async () => {
            const orderTransactionId = await getOrderTransactionId(orderId, AdminApiContext);
            const orderTransactionUpdateResponse = await AdminApiContext.post(
                `./_action/order_transaction/${orderTransactionId}/state/paid`,
                {},
            );
            expect(orderTransactionUpdateResponse.ok()).toBeTruthy();
        });

        await test.step('Verify that user can access the digital blog.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());

            // Download the digital blog and check if the content is equal to what was uploaded.
            await ShopCustomer.attemptsTo(DownloadDigitalProductFromOrderAndExpectContentToBe(fileContent));
        });
    },
);
