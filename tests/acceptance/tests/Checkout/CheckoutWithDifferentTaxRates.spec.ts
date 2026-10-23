import { test, formatPrice } from '@fixtures/AcceptanceTest';

interface TaxRate {
    id: string;
}

let taxRate0: TaxRate | undefined;
let taxRate7: TaxRate | undefined;
let taxRate19: TaxRate | undefined;

test.beforeAll(async ({ AdminApiContext, IdProvider }) => {
    const sharedTaxRates = [
        0,
        7,
        19,
    ].map((taxRate) => {
        const { uuid } = IdProvider.getIdPair();

        return {
            id: uuid,
            name: `Checkout tax ${taxRate}% ${uuid}`,
            taxRate,
        };
    });

    const response = await AdminApiContext.post('_action/sync', {
        data: {
            'create-shared-checkout-taxes': {
                entity: 'tax',
                action: 'upsert',
                payload: sharedTaxRates,
            },
        },
    });

    if (!response.ok()) {
        throw new Error('Could not create the shared checkout tax rates.');
    }

    [
        taxRate0,
        taxRate7,
        taxRate19,
    ] = sharedTaxRates;
});

test.afterAll(async ({ AdminApiContext }) => {
    const taxRates = [
        taxRate0,
        taxRate7,
        taxRate19,
    ].filter((taxRate): taxRate is TaxRate => taxRate !== undefined);

    if (taxRates.length === 0) {
        return;
    }

    const response = await AdminApiContext.post('_action/sync', {
        data: {
            'delete-shared-checkout-taxes': {
                entity: 'tax',
                action: 'delete',
                payload: taxRates,
            },
        },
    });

    if (!response.ok()) {
        throw new Error('Could not delete the shared checkout tax rates.');
    }
});

test(
    'As a user, I want to perform a checkout process with 19% tax rate with 2 same blogs.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        FrontendAccountOrder,
    }) => {
        const blogWithTaxRate19 = await TestDataService.createBasicProduct({}, taxRate19!.id);
        let orderNumber: string;

        await test.step('Add 2 identical blogs to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blogWithTaxRate19));
            await ShopCustomer.attemptsTo(AddProductToCart(blogWithTaxRate19, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(FrontendCheckoutConfirm.taxPrice).toContainText(formatPrice(3.19));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await FrontendCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(FrontendCheckoutFinish.taxPrice).toContainText(formatPrice(3.19));
        });

        await test.step('Navigate to users account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toContainText(formatPrice(3.19));
        });
    },
);

test(
    'As a user, I want to perform a checkout process with 7% tax rate with 2 different blogs.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        FrontendAccountOrder,
    }) => {
        const blog1WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7!.id);
        const blog2WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7!.id);
        let orderNumber: string;

        await test.step('Add 2 different blogs with same tax rate to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog1WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(blog1WithTaxRate7));
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog2WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(blog2WithTaxRate7));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(FrontendCheckoutConfirm.taxPrice).toContainText(formatPrice(1.3));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await FrontendCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(FrontendCheckoutFinish.taxPrice).toContainText(formatPrice(1.3));
        });

        await test.step('Navigate to users account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toContainText(formatPrice(1.3));
        });
    },
);

test(
    'As a user, I want to perform a checkout process with 7% and 19% tax rate per blog.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        FrontendAccountOrder,
    }) => {
        const blogWithTaxRate19 = await TestDataService.createBasicProduct({}, taxRate19!.id);
        const blog1WithTaxRate7 = await TestDataService.createBasicProduct({}, taxRate7!.id);
        let orderNumber: string;

        await test.step('Add 2 different blogs with different tax rates to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blogWithTaxRate19));
            await ShopCustomer.attemptsTo(AddProductToCart(blogWithTaxRate19, '2'));
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog1WithTaxRate7));
            await ShopCustomer.attemptsTo(AddProductToCart(blog1WithTaxRate7, '2'));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(FrontendCheckoutConfirm.taxPrice.nth(0)).toContainText(formatPrice(3.19));
            await ShopCustomer.expects(FrontendCheckoutConfirm.taxPrice.nth(1)).toContainText(formatPrice(1.31));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await FrontendCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(FrontendCheckoutFinish.taxPrice.nth(0)).toContainText(formatPrice(3.19));
            await ShopCustomer.expects(FrontendCheckoutFinish.taxPrice.nth(1)).toContainText(formatPrice(1.31));
        });

        await test.step('Navigate to users account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice.nth(0)).toContainText(formatPrice(3.19));
            await ShopCustomer.expects(orderLocators.taxPrice.nth(1)).toContainText(formatPrice(1.31));
        });
    },
);

test(
    'As a user, I want to perform a checkout process with 0% tax rate.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        FrontendCheckoutConfirm,
        FrontendCheckoutFinish,
        SelectShippingMethod,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        ConfirmTermsAndConditions,
        SelectPaymentMethod,
        SubmitOrder,
        FrontendAccountOrder,
    }) => {
        const blogWithTaxRate0 = await TestDataService.createBasicProduct({}, taxRate0!.id);
        let orderNumber: string;

        await test.step('Add one blog with tax rate to chart, proceed to checkout and validate on confirm page the tax price.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blogWithTaxRate0));
            await ShopCustomer.attemptsTo(AddProductToCart(blogWithTaxRate0));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(FrontendCheckoutConfirm.taxPrice).toContainText(formatPrice(0.0));
        });

        await test.step('Submit the order, navigate to checkout finish page and validate the tax price.', async () => {
            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SelectPaymentMethod('Invoice'));
            await ShopCustomer.attemptsTo(SelectShippingMethod('Standard'));

            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);
            orderNumber = await FrontendCheckoutFinish.getOrderNumber();

            await ShopCustomer.expects(FrontendCheckoutFinish.taxPrice).toContainText(formatPrice(0.0));
        });

        await test.step('Navigate to users account order page and validate the orders tax price.', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            const orderLocators = await FrontendAccountOrder.getOrderByOrderNumber(orderNumber);
            await ShopCustomer.presses(orderLocators.orderDetailButton);
            await ShopCustomer.expects(orderLocators.taxPrice).toContainText(formatPrice(0.0));
        });
    },
);
