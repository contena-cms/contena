import { test, expect, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'Registered shop user buys a blog.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        AdminApiContext,
        FrontendProductDetail,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SelectShippingMethod,
        SubmitOrder,
    }) => {
        const blog = await TestDataService.createBasicProduct();

        await ShopCustomer.attemptsTo(Login());

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.expects(FrontendProductDetail.page).toHaveTitle(
            `${blog.translated.name} | ${blog.blogNumber}`,
        );

        await ShopCustomer.attemptsTo(AddProductToCart(blog));
        await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());

        await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
        await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
        await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

        await ShopCustomer.expects(FrontendCheckoutConfirm.grandTotalPrice).toContainText(formatPrice(10.0));

        await ShopCustomer.attemptsTo(SubmitOrder());
        await ShopCustomer.expects(FrontendCheckoutFinish.grandTotalPrice).toContainText(formatPrice(10.0));

        const orderId = FrontendCheckoutFinish.getOrderId();

        TestDataService.addCreatedRecord('order', orderId);

        await test.step('Validate that the order was submitted successfully.', async () => {
            const orderResponse = await AdminApiContext.get(`order/${orderId}`);

            expect(orderResponse.ok()).toBeTruthy();

            const order = await orderResponse.json();

            expect(order.data).toEqual(
                expect.objectContaining({
                    price: expect.objectContaining({
                        totalPrice: 10,
                    }),
                    orderCustomer: expect.objectContaining({
                        email: DefaultSalesChannel.user.email,
                    }),
                }),
            );
        });
    },
);
