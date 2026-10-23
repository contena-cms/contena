import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'As a user, I expect to see and use a basic captcha function on the contact form.',
    {
        tag: [
            '@Frontend',
            '@Form',
            '@Captcha',
            '@Contact',
        ],
    },
    async ({ ShopCustomer, FrontendHome, FrontendContactForm, TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({
            'core.basicInformation.activeCaptchasV2': { basicCaptcha: { name: 'basicCaptcha', isActive: true } },
        });

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
            await ShopCustomer.fillsIn(FrontendContactForm.basicCaptchaInput, '1234');
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (!InstanceMeta.features['ACCESSIBILITY_TWEAKS'] && satisfies(InstanceMeta.version, '<6.7')) {
                await ShopCustomer.presses(FrontendContactForm.privacyPolicyCheckbox);
            }
        });

        await test.step('Validate the basic captcha is available.', async () => {
            await ShopCustomer.expects(FrontendContactForm.basicCaptcha).toBeVisible();
            await ShopCustomer.expects(FrontendContactForm.basicCaptchaImage).toBeVisible();
            await ShopCustomer.expects(FrontendContactForm.basicCaptchaRefreshButton).toBeVisible();
        });

        await ShopCustomer.expects(async () => {
            await test.step('Send and validate the unaccomplished contact form.', async () => {
                const formRoute = InstanceMeta.features['ACCESSIBILITY_TWEAKS']
                    ? '**/basic-captcha-validate'
                    : '**/form/contact';

                const formSubmitPromise = FrontendContactForm.page.waitForResponse(formRoute);
                await ShopCustomer.presses(FrontendContactForm.submitButton);
                await formSubmitPromise;

                await ShopCustomer.expects(FrontendContactForm.basicCaptchaInput).toHaveCSS(
                    'border-color',
                    'rgb(194, 0, 23)',
                );

                // eslint-disable-next-line playwright/no-conditional-in-test
                if (!InstanceMeta.features['ACCESSIBILITY_TWEAKS'] && satisfies(InstanceMeta.version, '<6.7')) {
                    await ShopCustomer.expects(FrontendContactForm.formAlert.last()).toBeVisible();
                    await ShopCustomer.expects(FrontendContactForm.formAlert.last()).toContainText(
                        'Incorrect input. Please try again.',
                    );
                } else {
                    await ShopCustomer.expects(FrontendContactForm.basicCaptchaInput).toHaveAccessibleDescription(
                        'Incorrect input. Please try again.',
                    );
                }
            });
        }).toPass({
            intervals: [
                1_000,
                2_500,
            ], // retry after 1 seconds, then every 2.5 seconds
        });
    },
);
