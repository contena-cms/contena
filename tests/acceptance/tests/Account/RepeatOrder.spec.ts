import { test } from '@fixtures/AcceptanceTest';

test(
    'As a user, I want to repeat a previous order via the frontend account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountOrder, FrontendOffCanvasCart, TestDataService, Login }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();
        const blogQuantity = 5;
        const order = await TestDataService.createOrder([{ blog: blog, quantity: blogQuantity }], user);

        // create another order with different quantity to ensure that the correct order is repeated
        await TestDataService.createOrder([{ blog: blog, quantity: 1 }], user);

        await ShopCustomer.attemptsTo(Login(user));
        await ShopCustomer.goesTo(FrontendAccountOrder.url());
        const orderItemLocators = await FrontendAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderRepeatButton);

        await ShopCustomer.expects(FrontendOffCanvasCart.itemCount).toBeVisible();
        await ShopCustomer.expects(FrontendOffCanvasCart.itemCount).toContainText('1 item');
        const cartProduct = await FrontendOffCanvasCart.getLineItemByProductNumber(blog.blogNumber);
        await ShopCustomer.expects(cartProduct.blogQuantityInput).toHaveValue(blogQuantity.toString());
    },
);
