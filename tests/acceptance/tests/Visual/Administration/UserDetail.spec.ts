import { test, setViewport, assertScreenshot, hideElements } from '@fixtures/AcceptanceTest';

test(
    'Visual: User Detail Page',
    { tag: '@Visual' },
    async ({ ShopAdmin, TestDataService, AdminCustomerListing, AdminCustomerDetail, DefaultSalesChannel }) => {
        const countryId = await DefaultSalesChannel.channel.countryId;
        const salutationId = await DefaultSalesChannel.channel.salutationId;
        const user = await TestDataService.createCustomer({
            firstName: 'John',
            lastName: 'Goldblum',
            userNumber: '12345',
            email: 'john.goldblum@example.com',
            createdAt: '2025-09-04T06:36:38.101+00:00',
            defaultShippingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                city: 'Schöppingen',
                street: 'Ebbinghoff 10',
                zipcode: '48624',
                countryId: countryId,
                salutationId: salutationId,
            },
            defaultBillingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                city: 'Schöppingen',
                street: 'Ebbinghoff 10',
                zipcode: '48624',
                countryId: countryId,
                salutationId: salutationId,
            },
        });

        await test.step('Creates a screenshot of the user listing page.', async () => {
            await ShopAdmin.goesTo(AdminCustomerListing.url());

            const userLineItem = await AdminCustomerListing.getCustomerByEmail(user.email);
            await ShopAdmin.expects(userLineItem.userName).toHaveAttribute(
                'href',
                `#/sw/user/detail/${user.id}?edit=false`,
            );

            //hide the first dynamic user for test stability
            const defaultCustomerRow = AdminCustomerListing.page
                .getByRole('row')
                .filter({ hasText: DefaultSalesChannel.user.email });
            await ShopAdmin.expects(defaultCustomerRow).toBeVisible();
            await hideElements(AdminCustomerListing.page, [defaultCustomerRow]);

            await setViewport(AdminCustomerListing.page, {
                waitForSelector: AdminCustomerListing.addCustomerButton,
                scrollableElementVertical: AdminCustomerListing.page.locator('.sw-page__main-content'),
                width: 2400,
            });

            await assertScreenshot(AdminCustomerListing.page, 'Listing-With-User.png');
        });

        await test.step('Creates a screenshot of the user from User Detail General Tab.', async () => {
            await ShopAdmin.goesTo(AdminCustomerDetail.url(user.id));
            await setViewport(AdminCustomerDetail.page, {
                waitForSelector: AdminCustomerDetail.editButton,
            });
            await assertScreenshot(AdminCustomerDetail.page, 'Detail-General-Tab.png');
        });

        await test.step('Creates a screenshot of the user from User Detail Adresses Tab.', async () => {
            await AdminCustomerDetail.addressesTab.click();
            await setViewport(AdminCustomerDetail.page, {
                waitForSelector: AdminCustomerDetail.editButton,
                width: 1800,
            });
            await assertScreenshot(AdminCustomerDetail.page, 'Detail-Addresses-Tab.png');
        });

        await test.step('Creates a screenshot of the user from User Detail Orders Tab.', async () => {
            await AdminCustomerDetail.ordersTab.click();
            await setViewport(AdminCustomerDetail.page, {
                waitForSelector: AdminCustomerDetail.editButton,
            });
            await assertScreenshot(AdminCustomerDetail.page, 'Detail-Orders-Tab.png');
        });
    },
);
