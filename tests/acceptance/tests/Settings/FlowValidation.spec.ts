import { test, expect } from '@fixtures/AcceptanceTest';
import { getCountryId, getSalutationId, setOrderStatus } from '@contena-ag/acceptance-test-suite';

test(
    'As an admin admin_user, I want that certain actions get executed based on the flow, so that I can automate the processes.',
    { tag: '@Flow' },
    async ({
        IdProvider,
        AdminApiContext,
        ShopAdmin,
        TestDataService,
        AdminOrderDetail,
        AdminCustomerDetail,
        CreateRuleBillingCountry,
        CreateFlowForValidation,
        WaitForFlowValidationSideEffects,
    }) => {
        // Test data setup
        const tagTrue = await TestDataService.createTag('Santa?');
        const tagFalse = await TestDataService.createTag('Probably Not Santa');
        const blog = await TestDataService.createBasicProduct();
        const countryId = await getCountryId('CX', AdminApiContext);
        const salutationId = await getSalutationId('mr', AdminApiContext);
        const userOverrides = {
            defaultBillingAddress: {
                firstName: 'Santa',
                lastName: 'Claus',
                city: 'Flying Fish Cove, Silver City',
                street: 'Seaview Drive 1',
                zipcode: '6798',
                countryId: countryId,
                salutationId: salutationId,
            },
        };
        const user = await TestDataService.createCustomer(userOverrides);

        const order = await TestDataService.createOrder([{ blog, quantity: 1 }], user);
        TestDataService.addCreatedRecord('order', order.id);
        const ruleConfig = { ruleId: IdProvider.getIdPair().uuid, countryId: countryId };
        await ShopAdmin.attemptsTo(CreateRuleBillingCountry(ruleConfig));

        const flowConfig = { flowId: IdProvider.getIdPair().uuid, ruleId: ruleConfig.ruleId, tagTrue, tagFalse };
        await ShopAdmin.attemptsTo(CreateFlowForValidation(flowConfig));
        // Ensure flow-loader cache sees the newly created flow before we trigger it.
        await TestDataService.clearCaches();

        await test.step('Set the order status to "in progress" to trigger the flow.', async () => {
            const orderState = await setOrderStatus(order.id, 'process', AdminApiContext);
            expect(orderState.ok()).toBeTruthy();
        });

        await test.step('Wait until flow side-effects are persisted via Admin API.', async () => {
            await ShopAdmin.attemptsTo(WaitForFlowValidationSideEffects({ orderId: order.id }));
        });

        await test.step('Validate order state and user tag via UI', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));
            await ShopAdmin.expects(AdminOrderDetail.orderStateInput).toHaveValue('In Progress');
            await ShopAdmin.expects(AdminOrderDetail.orderPaymentStatusInput).toHaveValue('Paid');
            await ShopAdmin.expects(AdminOrderDetail.orderDeliveryStatusInput).toHaveValue('Shipped (partially)');

            await ShopAdmin.goesTo(AdminCustomerDetail.url(user.id));
            await ShopAdmin.expects(AdminCustomerDetail.tagList).toContainText(tagTrue.name);
        });
    },
);
