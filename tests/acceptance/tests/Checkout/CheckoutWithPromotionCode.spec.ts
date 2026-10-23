import { test, expect, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'Registered shop user should be able to use promotion code during checkout.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        AdminApiContext,
        TestDataService,
        DefaultSalesChannel,
        FrontendCheckoutCart,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        // eslint-disable-next-line @typescript-eslint/no-unused-vars, no-unused-vars
        CartWithProductData,
        Login,
        AddPromotionCodeToCart,
        ProceedFromCartToCheckout,
        ConfirmTermsAndConditions,
        SubmitOrder,
    }) => {
        const promotion = await TestDataService.createPromotionWithCode();

        await ShopCustomer.attemptsTo(Login());

        await ShopCustomer.goesTo(FrontendCheckoutCart.url());

        // Value of test blog with price of €10 and quantity of 10.
        await ShopCustomer.expects(FrontendCheckoutCart.grandTotalPrice).toContainText(formatPrice(100.0));

        await ShopCustomer.attemptsTo(AddPromotionCodeToCart(promotion.name, promotion.code));
        await ShopCustomer.attemptsTo(ProceedFromCartToCheckout());
        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());

        // Value of test blog with price of €10 and quantity of 10 and 10% discount.
        await ShopCustomer.expects(FrontendCheckoutConfirm.grandTotalPrice).toContainText(formatPrice(90.0));

        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(FrontendCheckoutFinish.page.getByText(promotion.name)).toBeVisible();
        await ShopCustomer.expects(FrontendCheckoutFinish.grandTotalPrice).toContainText(formatPrice(90.0));

        const orderId = FrontendCheckoutFinish.getOrderId();

        TestDataService.addCreatedRecord('order', orderId);

        await test.step('Validate that the order was submitted successfully.', async () => {
            const orderResponse = await AdminApiContext.get(`order/${orderId}`);

            expect(orderResponse.ok()).toBeTruthy();

            const order = await orderResponse.json();

            expect(order.data).toEqual(
                expect.objectContaining({
                    price: expect.objectContaining({
                        totalPrice: 90,
                    }),
                    orderCustomer: expect.objectContaining({
                        email: DefaultSalesChannel.user.email,
                    }),
                }),
            );
        });
    },
);
