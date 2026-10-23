import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'As a user, the contact form only asks me to acknowledge the privacy policy.',
    {
        tag: [
            '@Form',
            '@Contact',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, FrontendContactForm, TestDataService }) => {
        const openContactForm = async (requireDataProtectionCheckbox: boolean) => {
            await TestDataService.setSystemConfig({
                'core.loginRegistration.requireDataProtectionCheckbox': requireDataProtectionCheckbox,
            });
            await ShopCustomer.goesTo(
                `${FrontendHome.url()}?privacy-checkbox=${requireDataProtectionCheckbox ? 'enabled' : 'disabled'}`,
            );
            await ShopCustomer.presses(FrontendHome.contactFormLink);
        };

        const privacyNotice = FrontendContactForm.page.locator('#cms-form-contact .privacy-notice');
        const dataProtectionCheckbox = privacyNotice.locator('input[name="acceptedDataProtection"]');

        await test.step('Shows a passive privacy-only notice without a checkbox', async () => {
            await openContactForm(false);
            await expect(privacyNotice).not.toContainText(/general terms and conditions|AGB/i);
            await expect(dataProtectionCheckbox).toHaveCount(0);
            await expect(privacyNotice).toContainText(/Please note|Bitte beachten/i);
        });

        await test.step('Shows a privacy-only checkbox when the checkbox is required', async () => {
            await openContactForm(true);
            await expect(privacyNotice).not.toContainText(/general terms and conditions|AGB/i);
            await expect(dataProtectionCheckbox).toBeVisible();
            await expect(privacyNotice).not.toContainText(/Please note|Bitte beachten/i);
        });
    },
);

test(
    'As a user, I want to fill out and submit the contact form.',
    {
        tag: [
            '@Form',
            '@Contact',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, FrontendContactForm, DefaultSalesChannel }) => {
        test.slow(); //Necessary for multiple retries due to rate limiting

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.presses(FrontendHome.contactFormLink);
            await ShopCustomer.expects(FrontendContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Fill out all necessary contact information.', async () => {
            await ShopCustomer.presses(FrontendContactForm.salutationSelect);
            await FrontendContactForm.salutationSelect.selectOption('Mr.');
            await ShopCustomer.fillsIn(FrontendContactForm.firstNameInput, 'John');
            await ShopCustomer.fillsIn(FrontendContactForm.lastNameInput, 'Doe');
            await ShopCustomer.fillsIn(FrontendContactForm.emailInput, 'mail@test.com');
            await ShopCustomer.fillsIn(FrontendContactForm.phoneInput, '0123456789');
            await ShopCustomer.fillsIn(FrontendContactForm.subjectInput, 'Test: Blog question');
            await ShopCustomer.fillsIn(
                FrontendContactForm.commentInput,
                'Test: Hello, I have a question about your blogs.',
            );
        });

        await ShopCustomer.expects(async () => {
            await test.step('Send and validate the contact form.', async () => {
                const contactFormPromise = FrontendContactForm.page.waitForResponse(
                    `${process.env.APP_URL}test-${DefaultSalesChannel.channel.id}/form/contact`,
                );
                await ShopCustomer.presses(FrontendContactForm.submitButton);
                const contactFormResponse = await contactFormPromise;
                expect(contactFormResponse.status()).toBe(200);

                await ShopCustomer.expects(FrontendContactForm.contactSuccessMessage).toBeVisible();
            });
        }).toPass({
            intervals: [30_000], // retry after 30 seconds
        });
    },
);

test(
    'As a user, I forgot to fill out some fields and should be informed about the missing ones.',
    {
        tag: [
            '@Form',
            '@Contact',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, FrontendContactForm, InstanceMeta }) => {
        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.presses(FrontendHome.contactFormLink);
            await ShopCustomer.expects(FrontendContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Send and validate the negative contact form result.', async () => {
            await ShopCustomer.presses(FrontendContactForm.submitButton);
            await ShopCustomer.expects(FrontendContactForm.cardTitle).toContainText('Contact');

            await ShopCustomer.expects(FrontendContactForm.salutationSelect).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendContactForm.firstNameInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendContactForm.lastNameInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendContactForm.emailInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendContactForm.phoneInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendContactForm.subjectInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(FrontendContactForm.commentInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

            // eslint-disable-next-line playwright/no-conditional-in-test
            if (InstanceMeta.features.ACCESSIBILITY_TWEAKS) {
                await ShopCustomer.expects(FrontendContactForm.formFieldFeedback).toHaveCount(7);
            }

            await ShopCustomer.expects(FrontendContactForm.contactSuccessMessage).not.toBeVisible();
        });
    },
);
