import { test, getLocale } from '@fixtures/AcceptanceTest';
import { getAddressDataFromLocale } from '../../helpers/locale-helpers';

test.describe('User Registration Form', () => {
    test.beforeEach(async ({ TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': {
                googleReCaptchaV2: {
                    name: 'googleReCaptchaV2',
                    isActive: false,
                    config: {
                        siteKey: '',
                        secretKey: '',
                        invisible: false,
                    },
                },
            },
        });
    });

    test(
        'As a user, the registration privacy notice matches the checkbox and legal guarantee configuration.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Frontend',
            ],
        },
        async ({ ShopCustomer, FrontendAccountLogin, TestDataService }) => {
            const privacyNotice = FrontendAccountLogin.page.locator('.register-form .privacy-notice');
            const dataProtectionCheckbox = privacyNotice.locator('input[name="acceptedDataProtection"]');
            const privacyLabel = privacyNotice.locator('label[for="acceptedDataProtection"], .data-protection-information');
            const legalGuaranteeNoticeParagraph = privacyNotice.locator('.legal-guarantee-notice');
            const legalGuaranteeNoticeLink = privacyNotice.locator('[data-bs-target="#legalGuaranteeNoticeModal"]');
            const legalGuaranteeNoticeModal = FrontendAccountLogin.page.locator('#legalGuaranteeNoticeModal');

            // Checkbox disabled, legal guarantee notice disabled: only the passive privacy notice is shown.
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': false,
                'core.cart.showLegalGuaranteeNotice': false,
            });
            await ShopCustomer.goesTo(`${FrontendAccountLogin.url()}?privacy-checkbox=disabled&guarantee=disabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
            await ShopCustomer.expects(privacyLabel).toContainText(/Please note|Bitte beachten/i);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
            await ShopCustomer.expects(FrontendAccountLogin.registerButton).toContainText(/Register|Registrieren/i);

            // Checkbox disabled, legal guarantee notice enabled: the notice is shown as its own paragraph.
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': true,
            });
            await ShopCustomer.goesTo(`${FrontendAccountLogin.url()}?privacy-checkbox=disabled&guarantee=enabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();

            await legalGuaranteeNoticeLink.click();

            await ShopCustomer.expects(legalGuaranteeNoticeModal).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeModal).toContainText(
                /Your legal guarantee rights|Ihre gesetzlichen Gewährleistungsrechte/i,
            );
            await ShopCustomer.expects(legalGuaranteeNoticeModal.getByRole('link')).toBeVisible();

            // Checkbox enabled, legal guarantee notice enabled: both the checkbox and the notice paragraph are shown.
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': true,
            });
            await ShopCustomer.goesTo(`${FrontendAccountLogin.url()}?privacy-checkbox=enabled&guarantee=enabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
            await ShopCustomer.expects(privacyLabel).not.toContainText(/Please note|Bitte beachten/i);
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toBeVisible();

            // Checkbox enabled, legal guarantee notice disabled: the checkbox stays, the notice paragraph disappears.
            await TestDataService.setSystemConfig({
                'core.cart.showLegalGuaranteeNotice': false,
            });
            await ShopCustomer.goesTo(`${FrontendAccountLogin.url()}?privacy-checkbox=enabled&guarantee=disabled`);

            await ShopCustomer.expects(dataProtectionCheckbox).toBeVisible();
            await ShopCustomer.expects(legalGuaranteeNoticeParagraph).toHaveCount(0);
            await ShopCustomer.expects(legalGuaranteeNoticeLink).toHaveCount(0);
        },
    );

    test(
        'As a user, I can perform a registration without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Frontend',
            ],
        },
        async ({ ShopCustomer, FrontendAccountLogin, FrontendAccount, IdProvider, Register }) => {
            const user = { email: `${IdProvider.getIdPair().uuid}@test.com` };

            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(user));

            await FrontendAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });

            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
        },
    );

    test(
        'As a user, I can perform a registration with full user data without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Frontend',
            ],
        },
        async ({ ShopCustomer, FrontendAccountLogin, FrontendAccount, IdProvider, TestDataService }) => {
            const locale = getLocale();
            const addressData = getAddressDataFromLocale(locale);

            const user = {
                salutation: 'Mr.',
                firstName: 'Jeff',
                lastName: 'Goldblum',
                email: `${IdProvider.getIdPair().uuid}@test.com`,
                password: 'contena',
                street: addressData.street,
                city: addressData.city,
                country: addressData.country,
                postalCode: addressData.postalCode,
            };

            await ShopCustomer.goesTo(FrontendAccountLogin.url());

            await FrontendAccountLogin.salutationSelect.selectOption(user.salutation);
            await FrontendAccountLogin.firstNameInput.fill(user.firstName);
            await FrontendAccountLogin.lastNameInput.fill(user.lastName);
            await FrontendAccountLogin.registerEmailInput.fill(user.email);
            await FrontendAccountLogin.registerPasswordInput.fill(user.password);

            await FrontendAccountLogin.streetAddressInput.fill(user.street);
            await FrontendAccountLogin.postalCodeInput.fill(user.postalCode);
            await FrontendAccountLogin.cityInput.fill(user.city);
            await FrontendAccountLogin.countryInput.selectOption({ label: user.country });

            await FrontendAccountLogin.registerButton.click();

            await FrontendAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });
            const registeredCustomer = await TestDataService.getCustomerByEmail(user.email);
            TestDataService.addCreatedRecord('user', registeredCustomer.id);

            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
        },
    );

    test(
        'As a user, I can perform a registration with validation errors without captcha protection.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Frontend',
            ],
        },
        async ({ ShopCustomer, FrontendAccountLogin, IdProvider, TestDataService }) => {
            const locale = getLocale();
            const addressData = getAddressDataFromLocale(locale);

            const user = {
                salutation: 'Mr.',
                firstName: 'Jeff',
                // lastName is missing intentionally
                email: `${IdProvider.getIdPair().uuid}@test.com`,
                password: 'contena',
                street: addressData.street,
                city: addressData.city,
                country: addressData.country,
                postalCode: addressData.postalCode,
            };

            await ShopCustomer.goesTo(FrontendAccountLogin.url());

            await FrontendAccountLogin.salutationSelect.selectOption(user.salutation);
            await FrontendAccountLogin.firstNameInput.fill(user.firstName);
            await FrontendAccountLogin.registerEmailInput.fill(user.email);
            await FrontendAccountLogin.registerPasswordInput.fill(user.password);

            await FrontendAccountLogin.streetAddressInput.fill(user.street);
            await FrontendAccountLogin.postalCodeInput.fill(user.postalCode);
            await FrontendAccountLogin.cityInput.fill(user.city);
            await FrontendAccountLogin.countryInput.selectOption({ label: user.country });

            await FrontendAccountLogin.registerButton.click();

            await ShopCustomer.expects(FrontendAccountLogin.lastNameInput).toHaveClass(/(^|\s)is-invalid(\s|$)/);

            await FrontendAccountLogin.lastNameInput.fill('Goldblum');
            await FrontendAccountLogin.registerButton.click();

            await FrontendAccountLogin.page.waitForURL('**/account', { waitUntil: 'commit' });
            const registeredCustomer = await TestDataService.getCustomerByEmail(user.email);
            TestDataService.addCreatedRecord('user', registeredCustomer.id);

            await ShopCustomer.expects(FrontendAccountLogin.page.getByText(user.email, { exact: true })).toBeVisible();
        },
    );
});
