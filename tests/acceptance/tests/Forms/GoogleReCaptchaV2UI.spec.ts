import { test } from '@fixtures/AcceptanceTest';
import {
    acceptTechnicalRequiredCookiesWithRecaptcha,
    verifyRecaptchaProtectionNotice,
    verifyRecaptchaScriptNotLoaded,
    waitForRecaptchaScriptLoaded,
} from '../../helpers/recaptcha-helpers';

const reCaptcha_V2_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
const reCaptcha_V2_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

test.describe('Google reCAPTCHA V2 UI', () => {
    test.beforeEach(async ({ InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');
    });

    test.describe('Visible reCAPTCHA', () => {
        test.beforeEach(async ({ TestDataService }) => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.activeCaptchasV2': {
                    googleReCaptchaV2: {
                        name: 'googleReCaptchaV2',
                        isActive: true,
                        config: {
                            siteKey: reCaptcha_V2_site_key,
                            secretKey: reCaptcha_V2_secret_key,
                            invisible: false,
                        },
                    },
                },
            });
        });

        test(
            'As a user, I can see the visible Google reCaptcha V2 is loaded and functional.',
            {
                tag: [
                    '@Form',
                    '@Captcha',
                    '@Frontend',
                ],
            },
            async ({ ShopCustomer, FrontendAccountLogin, acceptTechnicalRequiredCookies }) => {
                await ShopCustomer.goesTo(FrontendAccountLogin.url());

                await verifyRecaptchaScriptNotLoaded(FrontendAccountLogin.page, test, 'V2');

                await acceptTechnicalRequiredCookies();

                await waitForRecaptchaScriptLoaded(FrontendAccountLogin.page);

                // For visible V2, we need to check specific elements after script loads
                const reCaptchaContainer = FrontendAccountLogin.page.locator('.captcha-google-re-captcha-v2').first();
                const reCaptchaFrame = reCaptchaContainer.locator('iframe').first();
                const reCaptchaCheckbox = reCaptchaFrame.contentFrame().getByRole('checkbox', { name: `I'm not a robot` });

                await test.step('Verify the reCaptcha V2 is loaded and visible after cookie consent', async () => {
                    await ShopCustomer.expects(reCaptchaFrame).toBeVisible();
                    await ShopCustomer.expects(reCaptchaCheckbox).toBeVisible();
                });

                await test.step('Verify the reCaptcha V2 checkbox is functional', async () => {
                    // We cannot use presses() because the reCaptcha does not add a border/outline/box-shadow for its visible focus state.
                    await reCaptchaCheckbox.click();
                    await ShopCustomer.expects(reCaptchaCheckbox).toBeChecked();
                });
            },
        );
    });

    test.describe('Invisible reCAPTCHA', () => {
        test.beforeEach(async ({ TestDataService }) => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.activeCaptchasV2': {
                    googleReCaptchaV2: {
                        name: 'googleReCaptchaV2',
                        isActive: true,
                        config: {
                            siteKey: reCaptcha_V2_site_key,
                            secretKey: reCaptcha_V2_secret_key,
                            invisible: true,
                        },
                    },
                },
            });
        });

        test(
            'As a user, I can see the invisible Google reCaptcha V2 is loaded and shows the protection notice.',
            {
                tag: [
                    '@Form',
                    '@Captcha',
                    '@Frontend',
                ],
            },
            async ({ ShopCustomer, FrontendAccountLogin, acceptTechnicalRequiredCookies }) => {
                await ShopCustomer.goesTo(FrontendAccountLogin.url());

                await verifyRecaptchaScriptNotLoaded(FrontendAccountLogin.page, test, 'V2');

                await acceptTechnicalRequiredCookiesWithRecaptcha(
                    FrontendAccountLogin.page,
                    test,
                    () => acceptTechnicalRequiredCookies(),
                    'V2',
                );

                await verifyRecaptchaProtectionNotice(FrontendAccountLogin.page, test, 'V2');
            },
        );

        test(
            'As a user, I can see the invisible Google reCaptcha V2 is loaded in the contact form.',
            {
                tag: [
                    '@Form',
                    '@Contact',
                    '@Captcha',
                    '@Frontend',
                ],
            },
            async ({
                ShopCustomer,
                FrontendHome,
                FrontendContactForm,
                FrontendFooter,
                acceptTechnicalRequiredCookies,
            }) => {
                await test.step('Open the contact form modal on home page', async () => {
                    await ShopCustomer.goesTo(FrontendHome.url());

                    await verifyRecaptchaScriptNotLoaded(FrontendHome.page, test, 'V2');

                    await acceptTechnicalRequiredCookies();

                    await ShopCustomer.presses(FrontendFooter.footerContactFormLink);
                    await ShopCustomer.expects(FrontendContactForm.cardTitle).toContainText('Contact');
                });

                await waitForRecaptchaScriptLoaded(FrontendContactForm.page);
                await verifyRecaptchaProtectionNotice(FrontendContactForm.page, test, 'V2');
            },
        );
    });
});
