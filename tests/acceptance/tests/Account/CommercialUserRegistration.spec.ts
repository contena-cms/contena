import { test } from '@fixtures/AcceptanceTest';

test(
    'New users can register as commercial users in the Frontend.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, FrontendAccount, IdProvider, Register, TestDataService }) => {
        const uuid = IdProvider.getIdPair().uuid;
        const user = { isCommercial: true, email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });

        await test.step('Register as a commercial user', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.accountTypeSelect);
            await FrontendAccountLogin.accountTypeSelect.selectOption('Commercial');
            await ShopCustomer.attemptsTo(Register(user));
        });

        await test.step('Verify successful commercial user registration', async () => {
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
            await ShopCustomer.expects(FrontendAccount.page.getByText('contena - Operations VAT Reg')).toContainText(
                user.vatRegNo.toUpperCase(),
            );
        });
    },
);

test(
    'New users cannot register as commercial users without a VAT Reg.No.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, Register, TestDataService, DefaultSalesChannel }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
        const country = await TestDataService.createCountry({ vatIdRequired: true });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.channel.id, country.id);
        const user = { isCommercial: true, country: country.name, vatRegNo: '' };

        await test.step('Attempt to register without a VAT Reg.No.', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.accountTypeSelect);
            await FrontendAccountLogin.accountTypeSelect.selectOption('Commercial');
            await ShopCustomer.attemptsTo(Register(user));
        });

        await test.step('Verify registration is blocked due to missing VAT Reg.No.', async () => {
            await ShopCustomer.expects(FrontendAccountLogin.page.getByText("I'm a new user!")).toBeVisible();
            await ShopCustomer.expects(FrontendAccountLogin.vatRegNoInput).toHaveAttribute('aria-required');
            await ShopCustomer.expects(FrontendAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
        });
    },
);

test(
    'New users cannot register as commercial users with an invalid VAT Reg.No.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, IdProvider, Register, TestDataService, DefaultSalesChannel }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
        const country = await TestDataService.createCountry({ checkVatIdPattern: true, vatIdPattern: 'DE\\d{9}' });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.channel.id, country.id);
        const uuid = IdProvider.getIdPair().uuid;
        const user = { isCommercial: true, vatRegNo: `${uuid}-VatId`, country: country.name };

        await test.step('Attempt to register with an invalid VAT Reg.No.', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.accountTypeSelect);
            await FrontendAccountLogin.accountTypeSelect.selectOption('Commercial');
            await ShopCustomer.attemptsTo(Register(user));
        });

        await test.step('Verify registration is blocked due to invalid VAT Reg.No. format', async () => {
            await ShopCustomer.expects(FrontendAccountLogin.page.getByText("I'm a new user!")).toBeVisible();
            await ShopCustomer.expects(FrontendAccountLogin.vatRegNoInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendAccountLogin.page.locator('.invalid-feedback')).toContainText(
                'The VAT Reg.No. you have entered does not have the correct format.',
            );
        });
    },
);
