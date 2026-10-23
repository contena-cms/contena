import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Commercial users keep company and VAT Reg.No. when saving the profile without the account type selection.',
    {
        tag: [
            '@Account',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, AdminApiContext, FrontendAccountProfile, Login }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': false });
        const user = await TestDataService.createCustomer({
            accountType: 'business',
            company: 'Example Inc.',
            vatIds: ['DE123456789'],
        });

        const profileForm = FrontendAccountProfile.page.locator('#profilePersonalForm');
        const companyInput = profileForm.locator('input[name="company"]');
        const vatIdInput = profileForm.locator('#vatIds');

        await test.step('The profile shows company and VAT Reg.No. of the commercial user', async () => {
            await ShopCustomer.attemptsTo(Login(user));
            await ShopCustomer.goesTo(FrontendAccountProfile.url());

            await ShopCustomer.expects(companyInput).toHaveValue('Example Inc.');
            await ShopCustomer.expects(vatIdInput).toHaveValue('DE123456789');
        });

        await test.step('Changing only the first name keeps account type, company and VAT Reg.No.', async () => {
            await ShopCustomer.fillsIn(FrontendAccountProfile.firstNameInput, 'Jane');
            await ShopCustomer.presses(FrontendAccountProfile.saveProfileButton);
            await ShopCustomer.expects(FrontendAccountProfile.firstNameInput).toHaveValue('Jane');

            const response = await AdminApiContext.get(`user/${user.id}`);
            expect(response.ok()).toBeTruthy();

            const { data } = await response.json();
            expect(data).toEqual(
                expect.objectContaining({
                    firstName: 'Jane',
                    accountType: 'business',
                    company: 'Example Inc.',
                    vatIds: ['DE123456789'],
                }),
            );
        });
    },
);
