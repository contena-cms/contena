import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'As a user, I must be able to change my email via account.',
    {
        tag: [
            '@Account',
            '@Frontend',
        ],
    },
    async ({ IdProvider, ShopCustomer, FrontendAccount, FrontendAccountLogin, FrontendAccountProfile, Register }) => {
        const user = { email: IdProvider.getIdPair().uuid + '@test.com', password: IdProvider.getIdPair().uuid };
        const invalidEmail = 'invalidEmailWithoutAtSymbol';
        const newEmail = IdProvider.getIdPair().uuid + '@test.com';

        await test.step('Register a valid account', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(user));
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
        });

        await test.step('Attempt to change email to an invalid address', async () => {
            await ShopCustomer.goesTo(FrontendAccountProfile.url());
            await ShopCustomer.presses(FrontendAccountProfile.changeEmailButton);
            await ShopCustomer.expects(FrontendAccountProfile.emailAddressInput).toBeVisible();
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailAddressInput, invalidEmail);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailAddressConfirmInput, invalidEmail);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailConfirmPasswordInput, user.password);
            await ShopCustomer.presses(FrontendAccountProfile.saveEmailAddressButton);
            await ShopCustomer.expects(FrontendAccountProfile.emailValidationAlert).toBeVisible();
        });

        await test.step('Attempt to change email to the same address', async () => {
            await ShopCustomer.goesTo(FrontendAccountProfile.url());
            await ShopCustomer.presses(FrontendAccountProfile.changeEmailButton);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailAddressInput, user.email);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailAddressConfirmInput, user.email);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailConfirmPasswordInput, user.password);
            await ShopCustomer.presses(FrontendAccountProfile.saveEmailAddressButton);
            await ShopCustomer.expects(FrontendAccountProfile.emailUpdateFailureAlert).toBeVisible();
        });

        await test.step('Change email to a new valid address', async () => {
            await ShopCustomer.goesTo(FrontendAccountProfile.url());
            await ShopCustomer.presses(FrontendAccountProfile.changeEmailButton);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailAddressInput, newEmail);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailAddressConfirmInput, newEmail);
            await ShopCustomer.fillsIn(FrontendAccountProfile.emailConfirmPasswordInput, user.password);
            await ShopCustomer.presses(FrontendAccountProfile.saveEmailAddressButton);
            await ShopCustomer.expects(FrontendAccountProfile.emailUpdateMessage).toBeVisible();
            await ShopCustomer.expects(FrontendAccountProfile.loginDataEmailAddress).toContainText(newEmail);
        });

        await test.step('Verify login with old email fails', async () => {
            await ShopCustomer.presses(FrontendAccountLogin.logoutLink);
            await ShopCustomer.expects(FrontendAccountLogin.successAlert).toBeVisible();
            await ShopCustomer.fillsIn(FrontendAccountLogin.emailInput, user.email);
            await ShopCustomer.fillsIn(FrontendAccountLogin.passwordInput, user.password);
            await ShopCustomer.presses(FrontendAccountLogin.loginButton);
            await ShopCustomer.expects(FrontendAccountLogin.invalidCredentialsAlert).toBeVisible();
        });

        await test.step('Verify login with new email', async () => {
            await ShopCustomer.fillsIn(FrontendAccountLogin.emailInput, newEmail);
            await ShopCustomer.fillsIn(FrontendAccountLogin.passwordInput, user.password);
            await ShopCustomer.presses(FrontendAccountLogin.loginButton);
            await FrontendAccount.page.waitForURL('**/account', { waitUntil: 'commit' });
            await ShopCustomer.expects(FrontendAccount.personalDataCardTitle).toBeVisible();
        });
    },
);

test(
    'As a user, I must be able to change my password via account.',
    {
        tag: [
            '@Account',
            '@Frontend',
        ],
    },
    async ({
        IdProvider,
        InstanceMeta,
        ShopCustomer,
        FrontendAccount,
        FrontendAccountLogin,
        FrontendAccountProfile,
        Register,
    }) => {
        const user = { email: IdProvider.getIdPair().uuid + '@test.com', password: IdProvider.getIdPair().uuid };
        const invalidPassword = { password: 'short' }; // Invalid: less than 8 characters
        const newPassword = IdProvider.getIdPair().uuid;

        await test.step('Register a new account', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(user));
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
        });

        await test.step('Attempt to change password to an invalid (short) password', async () => {
            await ShopCustomer.goesTo(FrontendAccountProfile.url());
            await ShopCustomer.presses(FrontendAccountProfile.changePasswordButton);
            await ShopCustomer.expects(FrontendAccountProfile.newPasswordInput).toBeVisible();
            await ShopCustomer.fillsIn(FrontendAccountProfile.newPasswordInput, invalidPassword.password);
            await ShopCustomer.fillsIn(FrontendAccountProfile.newPasswordConfirmInput, invalidPassword.password);
            await ShopCustomer.fillsIn(FrontendAccountProfile.currentPasswordInput, user.password);
            await ShopCustomer.presses(FrontendAccountProfile.saveNewPasswordButton);

            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
                await FrontendAccountProfile.saveNewPasswordButton.click();
            } else {
                await ShopCustomer.presses(FrontendAccountProfile.saveNewPasswordButton);
            }

            await ShopCustomer.expects(FrontendAccountProfile.passwordUpdateFailureAlert).toBeVisible();
        });

        await test.step('Successfully change password to a valid password', async () => {
            await ShopCustomer.goesTo(FrontendAccountProfile.url());
            await ShopCustomer.presses(FrontendAccountProfile.changePasswordButton);
            await ShopCustomer.expects(FrontendAccountProfile.newPasswordInput).toBeVisible();
            await ShopCustomer.fillsIn(FrontendAccountProfile.newPasswordInput, newPassword);
            await ShopCustomer.fillsIn(FrontendAccountProfile.newPasswordConfirmInput, newPassword);
            await ShopCustomer.fillsIn(FrontendAccountProfile.currentPasswordInput, user.password);
            await ShopCustomer.presses(FrontendAccountProfile.saveNewPasswordButton);
            await ShopCustomer.expects(FrontendAccountProfile.passwordUpdateMessage).toBeVisible();
        });

        await test.step('Verify login with old password fails', async () => {
            await ShopCustomer.presses(FrontendAccountLogin.logoutLink);
            await ShopCustomer.expects(FrontendAccountLogin.successAlert).toBeVisible();
            await ShopCustomer.fillsIn(FrontendAccountLogin.emailInput, user.email);
            await ShopCustomer.fillsIn(FrontendAccountLogin.passwordInput, user.password);
            await ShopCustomer.presses(FrontendAccountLogin.loginButton);
            await ShopCustomer.expects(FrontendAccountLogin.invalidCredentialsAlert).toBeVisible();
        });

        await test.step('Verify login with new password', async () => {
            await ShopCustomer.fillsIn(FrontendAccountLogin.emailInput, user.email);
            await ShopCustomer.fillsIn(FrontendAccountLogin.passwordInput, newPassword);
            await ShopCustomer.presses(FrontendAccountLogin.loginButton);
            await FrontendAccount.page.waitForURL('**/account', { waitUntil: 'commit' });
            await ShopCustomer.expects(FrontendAccount.personalDataCardTitle).toBeVisible();
        });
    },
);
