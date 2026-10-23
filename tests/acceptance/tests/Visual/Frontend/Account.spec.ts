import { test } from '@fixtures/AcceptanceTest';

test(
    'Visual: Frontend Account Pages',
    { tag: '@Visual' },
    async ({
        ShopCustomer,
        DefaultSalesChannel,
        TestDataService,
        FrontendAccountLogin,
        FrontendAccount,
        FrontendAccountProfile,
        FrontendAccountAddresses,
        FrontendAccountOrder,
        Login,
    }) => {
        const countryId = await DefaultSalesChannel.channel.countryId;
        const salutationId = await DefaultSalesChannel.channel.salutationId;
        const user = await TestDataService.createCustomer({
            firstName: 'John',
            lastName: 'Goldblum',
            userNumber: '12345',
            email: 'johngoldblum@example.com',
            password: 'contena',
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

        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Create screenshot of account login page in frontend.', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.expects(FrontendAccountLogin.page).toHaveScreenshot('Account-Login-Page.png', {
                fullPage: true,
            });
        });

        await test.step('Create screenshot of account overview page in frontend.', async () => {
            await ShopCustomer.attemptsTo(Login(user));
            await ShopCustomer.expects(FrontendAccount.navigation.overviewLink).toBeVisible();
            await ShopCustomer.expects(FrontendAccount.page).toHaveScreenshot('Account-Overview-Page.png', {
                fullPage: true,
            });
        });

        await test.step('Create screenshot of account profile page in frontend.', async () => {
            await FrontendAccount.navigation.yourProfileLink.click();
            await ShopCustomer.expects(FrontendAccountProfile.changePasswordButton).toBeVisible();
            await ShopCustomer.expects(FrontendAccountProfile.page).toHaveScreenshot('Account-Profile-Page.png', {
                fullPage: true,
            });
        });

        await test.step('Create screenshot of account addresses page in frontend.', async () => {
            await FrontendAccountProfile.navigation.addressesLink.click();
            await ShopCustomer.expects(FrontendAccountAddresses.addNewAddressButton).toBeVisible();
            await ShopCustomer.expects(FrontendAccountAddresses.page).toHaveScreenshot('Account-Addresses-Page.png', {
                fullPage: true,
            });
        });

        await test.step('Create screenshot of account order page in frontend.', async () => {
            await FrontendAccountAddresses.navigation.ordersLink.click();
            await ShopCustomer.expects(FrontendAccountOrder.noOrdersAlert).toBeVisible();
            await ShopCustomer.expects(FrontendAccountOrder.page).toHaveScreenshot('Account-Order-Page.png', {
                fullPage: true,
            });
        });
    },
);
