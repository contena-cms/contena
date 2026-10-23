import { test, formatPrice } from '@fixtures/AcceptanceTest';

test(
    'As an admin, I want to create documents and make sure they contain certain infos.',
    { tag: '@Documents' },
    async ({
        ShopAdmin,
        TestDataService,
        AdminDocumentListing,
        AdminDocumentDetail,
        AdminOrderDetail,
        ShopCustomer,
        FrontendAccountOrder,
        Login,
        AddCreditItem,
        CreateDocument,
    }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();

        const order = await TestDataService.createOrder([{ blog, quantity: 1 }], user);

        await test.step('Go to documents settings page and activate documents in user accounts', async () => {
            await ShopAdmin.goesTo(AdminDocumentListing.url());

            await AdminDocumentListing.invoiceLink.click();
            await ShopAdmin.expects(AdminDocumentDetail.documentTypeSelect).toContainText('Invoice');

            await AdminDocumentDetail.displayDocumentInMyAccountSwitch.check();
            await AdminDocumentDetail.saveButton.click();

            await ShopAdmin.attemptsTo(AddCreditItem(order.id));
            await CreateDocument({
                orderId: order.id,
                type: 'invoice',
            })();
        });

        await test.step('Go to documents tab and send invoice', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));

            const documentRow = AdminOrderDetail.getDocumentRow(0);
            const markAsSent = AdminOrderDetail.page.locator('.sw-order-document-card__context-button-mark-sent');

            await ShopAdmin.expects(documentRow.row).toBeVisible();
            await documentRow.contextMenuButton.click();

            await ShopAdmin.expects(markAsSent).toBeVisible();
            await markAsSent.click();

            await ShopAdmin.expects(markAsSent).not.toBeVisible();
            await ShopAdmin.expects(documentRow.sentCheckmark).toBeVisible();
        });

        await test.step('Log into user account and check the order document', async () => {
            await ShopCustomer.attemptsTo(Login(user));
            await ShopCustomer.goesTo(FrontendAccountOrder.url());

            await ShopCustomer.expects(FrontendAccountOrder.orderExpandButton).toBeVisible();
            await FrontendAccountOrder.orderExpandButton.click();

            await ShopCustomer.expects(FrontendAccountOrder.orderDetails).toBeVisible();
            await FrontendAccountOrder.invoiceHTML.click();
            await ShopCustomer.expects(FrontendAccountOrder.creditItem).toContainText(formatPrice(1.0));
        });
    },
);
