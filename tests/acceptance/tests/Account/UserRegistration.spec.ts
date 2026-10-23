import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'As a new user, I must be able to register in the Frontend.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, FrontendAccount, IdProvider, Register }) => {
        const user = { email: IdProvider.getIdPair().uuid + '@test.com' };

        await ShopCustomer.goesTo(FrontendAccountLogin.url());
        await ShopCustomer.attemptsTo(Register(user));
        await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
    },
);

test(
    'As a new user, I should not be able to register with empty postal code when it is required.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, Register, TestDataService, DefaultSalesChannel, InstanceMeta }) => {
        const country = await TestDataService.createCountry({ postalCodeRequired: true });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.channel.id, country.id);
        const user = { country: country.name, postalCode: '' };

        await test.step('Attempt to register with missing postal code', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(user));
        });

        await test.step('Validate registration is blocked due to missing postal code', async () => {
            await ShopCustomer.expects(FrontendAccountLogin.page.getByText("I'm a new user!")).toBeVisible();
            await ShopCustomer.expects(
                FrontendAccountLogin.page.locator('label[for="billingAddressAddressZipcode"]'),
            ).toContainText('*');
            await ShopCustomer.expects(FrontendAccountLogin.postalCodeInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

            // eslint-disable-next-line playwright/no-conditional-in-test
            if (!satisfies(InstanceMeta.version, '<6.7')) {
                await ShopCustomer.expects(FrontendAccountLogin.page.locator('.invalid-feedback')).toContainText(
                    'Input should not be empty.',
                );
            }
        });
    },
);
