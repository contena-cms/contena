import { test } from '@fixtures/AcceptanceTest';
import { screenshotDocument, DocumentTypes } from '@helpers/document-helpers';

test(
    'Visual: Document PDFs should match expected appearance',
    { tag: '@Visual @Documents' },
    async ({
        ShopAdmin,
        TestDataService,
        DefaultSalesChannel,
        AdminOrderDetail,
        AddCreditItem,
        CreateDocument,
        IdProvider,
    }) => {
        let invoiceDocumentId: string;

        const blog = {
            ...(await TestDataService.createBasicProduct()),
            blogNumber: 'TEST-BLOG',
            name: 'Test Blog',
        };

        await test.step('should match expected appearance for each document type', async ({}) => {
            const order = await TestDataService.createOrder([{ blog, quantity: 1 }], DefaultSalesChannel.user);

            const documents: DocumentTypes[] = [
                'invoice',
                'credit_note',
                'delivery_note',
                'cancellation_invoice',
                'zugferd_embedded_invoice',
                'zugferd_embedded_cancellation_invoice',
                'zugferd_embedded_credit_note',
            ];

            const requiresCreditNote: DocumentTypes[] = [
                'credit_note',
                'zugferd_embedded_credit_note',
            ];

            for (const type of documents) {
                if (requiresCreditNote.includes(type)) {
                    await ShopAdmin.attemptsTo(AddCreditItem(order.id));
                }

                const { documentId } = await CreateDocument({
                    orderId: order.id,
                    type,
                    referencedDocumentId: invoiceDocumentId,
                })();

                if (type === 'invoice') {
                    invoiceDocumentId = documentId;
                }

                await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));

                await AdminOrderDetail.getDocumentRow(0).contextMenuButton.click();
                await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();

                await screenshotDocument(
                    `${type}-document`,
                    AdminOrderDetail.contextMenuOpenDocument,
                    ShopAdmin.expects,
                    type,
                );
            }
        });

        await test.step('should match expected appearance for invoice with multiple pages', async ({}) => {
            const blogsForOrder = Array.from({ length: 15 }, () => blog).map((blog, i) => ({
                ...blog,
                _uniqueIdentifier: IdProvider.getIdPair().uuid,
                blogNumber: `TEST-BLOG-${i + 1}`,
                name: `Test Blog ${i + 1}`,
            }));

            const order = await TestDataService.createOrder(
                blogsForOrder.map((blog) => ({ blog, quantity: 1 })),
                DefaultSalesChannel.user,
            );

            await CreateDocument({
                orderId: order.id,
                type: 'invoice',
            })();

            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'), true);

            await AdminOrderDetail.getDocumentRow(0).contextMenuButton.click();
            await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();

            await screenshotDocument(
                'invoice-document-multiple-pages',
                AdminOrderDetail.contextMenuOpenDocument,
                ShopAdmin.expects,
                'invoice',
            );
        });
    },
);
