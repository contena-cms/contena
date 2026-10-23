import { test } from '@fixtures/AcceptanceTest';

test(
    'Users are able to cancel orders in frontend account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountOrder, TestDataService, Login }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ blog: blog, quantity: 5 }], user);

        const untouchedOrder = await TestDataService.createOrder([{ blog: blog, quantity: 1 }], user);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountOrder.url());

        const untouchedOrderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(untouchedOrder.orderNumber);
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText('Open');

        const orderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderCancelButton);
        await ShopCustomer.expects(FrontendAccountOrder.dialogOrderCancel).toBeFocused();
        await ShopCustomer.presses(FrontendAccountOrder.dialogOrderCancelButton);
        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
        // ensure other order is unaffected
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText('Open');
    },
);

test(
    'Users are able to cancel orders on the final checkout page in frontend account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountOrder, TestDataService, Login, FrontendCheckoutOrderEdit }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ blog: blog, quantity: 5 }], user);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        const orderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.presses(FrontendCheckoutOrderEdit.orderCancelButton);
        await ShopCustomer.expects(FrontendCheckoutOrderEdit.dialogOrderCancel).toBeFocused();
        await ShopCustomer.presses(FrontendCheckoutOrderEdit.dialogOrderCancelButton);
        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
    },
);

test(
    'Users are not able to cancel orders on the final checkout page in frontend account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountOrder, TestDataService, Login, FrontendCheckoutOrderEdit }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ blog: blog, quantity: 5 }], user);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        const orderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.expects(FrontendCheckoutOrderEdit.orderCancelButton).not.toBeVisible();
    },
);

test(
    'Users are not able to cancel orders in frontend account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountOrder, TestDataService, Login }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ blog: blog, quantity: 5 }], user);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        const orderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.expects(orderItemLocators.orderCancelButton).not.toBeVisible();
    },
);
