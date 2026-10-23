import { test } from '@fixtures/AcceptanceTest';

test(
    'Users can update the payment method for an existing order in the frontend account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        FrontendAccountOrder,
        FrontendCheckoutOrderEdit,
        TestDataService,
        Login,
        SelectPaymentMethod,
    }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ blog: blog, quantity: 5 }], user);

        const untouchedOrder = await TestDataService.createOrder([{ blog: blog, quantity: 1 }], user);

        const newPaymentMethod = await TestDataService.createBasicPaymentMethod({ afterOrderEnabled: true });
        await TestDataService.assignSalesChannelPaymentMethod(TestDataService.defaultSalesChannel.id, newPaymentMethod.id);

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountOrder.url());

        const untouchedOrderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(untouchedOrder.orderNumber);
        await ShopCustomer.expects(untouchedOrderItemLocators.orderPaymentMethod).toContainText('Invoice');

        const orderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');

        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);

        await ShopCustomer.attemptsTo(SelectPaymentMethod(newPaymentMethod.name));
        await ShopCustomer.presses(FrontendCheckoutOrderEdit.completePaymentButton);

        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(newPaymentMethod.name);
        // check that other order is not touched
        await ShopCustomer.expects(untouchedOrderItemLocators.orderPaymentMethod).toContainText('Invoice');
    },
);
