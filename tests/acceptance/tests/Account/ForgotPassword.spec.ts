import { test } from '@fixtures/AcceptanceTest';

test(
    'As a user, I can request a new password with existing user email address.',
    {
        tag: [
            '@Account',
            '@Password',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, FrontendAccountRecover, TestDataService }) => {
        const user = await TestDataService.createCustomer();
        await test.step('Navigate to the login page and click on forgot password', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.forgotPasswordLink);
        });

        await test.step('Fill in the user email and request a password reset', async () => {
            await ShopCustomer.fillsIn(FrontendAccountRecover.emailInput, user.email);
            await ShopCustomer.presses(FrontendAccountRecover.requestEmailButton);
        });

        await test.step('Verify the password reset email sent message and navigate back to login', async () => {
            await ShopCustomer.expects(FrontendAccountRecover.passwordResetEmailSentMessage).toBeVisible();
            await ShopCustomer.presses(FrontendAccountRecover.backButton);
            await ShopCustomer.expects(FrontendAccountLogin.loginButton).toBeVisible();
        });
    },
);

test(
    'As a user, I can request a new password without existing user email address.',
    {
        tag: [
            '@Account',
            '@Password',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendAccountLogin, FrontendAccountRecover }) => {
        const uniqueEmail = `forgot-password-${test.info().repeatEachIndex}-${test.info().retry}-${Date.now()}@email.net`;

        await test.step('Navigate to login page and initiate password recovery', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.forgotPasswordLink);
        });

        await test.step('Attempt to request password reset without entering an email', async () => {
            await ShopCustomer.presses(FrontendAccountRecover.requestEmailButton);
            await ShopCustomer.expects(FrontendAccountRecover.passwordResetEmailSentMessage).not.toBeVisible();
        });

        await test.step('Request password reset with a non-existing email', async () => {
            await ShopCustomer.fillsIn(FrontendAccountRecover.emailInput, uniqueEmail);
            await ShopCustomer.presses(FrontendAccountRecover.requestEmailButton);
            // Verify that the success message is shown for security reasons
            await ShopCustomer.expects(FrontendAccountRecover.passwordResetEmailSentMessage).toBeVisible();
        });
    },
);

test(
    'As a user, I can reset my password using the password recovery process for an existing account and successfully log in with the new password.',
    {
        tag: [
            '@Account',
            '@Password',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        FrontendAccountLogin,
        FrontendAccountRecover,
        TestDataService,
        MailpitApiContext,
        InstanceMeta,
        Login,
        DefaultSalesChannel,
    }) => {
        test.skip(InstanceMeta.isSaaS || InstanceMeta.isPaaS, 'Skipping test because it requires a local mailpit instance.');

        let passwordResetLink = '';
        const newPassword = 'new-password';
        const user = await TestDataService.createCustomer();

        await test.step('Navigate to the login page and click on forgot password', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.presses(FrontendAccountLogin.forgotPasswordLink);
        });

        await test.step('Fill in the user email and request a password reset', async () => {
            await ShopCustomer.fillsIn(FrontendAccountRecover.emailInput, user.email);
            await ShopCustomer.presses(FrontendAccountRecover.requestEmailButton);
        });

        await test.step('Verify the password reset email sent message and navigate back to login', async () => {
            await ShopCustomer.expects(FrontendAccountRecover.passwordResetEmailSentMessage).toBeVisible();
            await ShopCustomer.presses(FrontendAccountRecover.backButton);
            await ShopCustomer.expects(FrontendAccountLogin.loginButton).toBeVisible();
        });

        await test.step('Verify email headers and content', async () => {
            const emailHeaders = await MailpitApiContext.getEmailHeaders(user.email);
            const emailBody = await MailpitApiContext.getEmailBody(user.email);
            ShopCustomer.expects(emailHeaders.subject).toContain('Password recovery');
            ShopCustomer.expects(emailHeaders.toName).toContain(`${user.firstName} ${user.lastName}`);
            ShopCustomer.expects(emailHeaders.toAddress).toContain(user.email);
            ShopCustomer.expects(emailBody).toContain(`Hello ${user.firstName} ${user.lastName},`);
            ShopCustomer.expects(emailBody).toContain(
                `You have requested a new password for your ${DefaultSalesChannel.channel.name} account.`,
            );
            ShopCustomer.expects(emailBody).toContain('Click on the following link to reset your password:');
            ShopCustomer.expects(emailBody).toContain('This link is valid for the next 2 hours.');
            ShopCustomer.expects(emailBody).toContain(
                "If you don't want to reset your password, ignore this email and no changes will be made.",
            );
            ShopCustomer.expects(emailBody).toContain('Yours sincerely');
            ShopCustomer.expects(emailBody).toContain(`Your ${DefaultSalesChannel.channel.name} team`);
        });

        await test.step('Update password using link in the email and login with new password successfully', async () => {
            passwordResetLink = await MailpitApiContext.getLinkFromMail(user.email);
            await ShopCustomer.goesTo(FrontendAccountRecover.url(passwordResetLink));
            await ShopCustomer.fillsIn(FrontendAccountRecover.newPasswordInput, newPassword);
            await ShopCustomer.fillsIn(FrontendAccountRecover.newPasswordConfirmInput, newPassword);
            await ShopCustomer.presses(FrontendAccountRecover.changePasswordButton);
            await ShopCustomer.expects(FrontendAccountLogin.passwordUpdatedAlert).toBeVisible();
            user.password = newPassword;
            await ShopCustomer.expects(FrontendAccountLogin.loginButton).toBeVisible();
            await ShopCustomer.attemptsTo(Login(user));
        });

        await test.step('Verify that the recovery link is invalid after used. Request form displays so admin_user can request again.', async () => {
            await ShopCustomer.goesTo(FrontendAccountRecover.url(passwordResetLink));
            await ShopCustomer.expects(FrontendAccountRecover.invalidLinkMessage).toBeVisible();
            await ShopCustomer.expects(FrontendAccountRecover.emailInput).toBeVisible();
            await ShopCustomer.expects(FrontendAccountRecover.requestEmailButton).toBeVisible();
        });
    },
);
