import { test } from '@fixtures/AcceptanceTest';
import {
    verifyRecaptchaProtectionNotice,
    verifyRecaptchaScriptNotLoaded,
    waitForRecaptchaScriptLoaded,
} from '../../helpers/recaptcha-helpers';

const reCaptcha_V2_site_key = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
const reCaptcha_V2_secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

// Store original config for cleanup
let originalConfig: Record<string, unknown> = {};

test.describe('Google reCAPTCHA V2 Login Tests', () => {
    test.beforeEach(async ({ TestDataService, InstanceMeta }) => {
        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        // Get and store current config
        const getCurrentConfig = await TestDataService.AdminApiClient.get(
            '_action/system-config?domain=core.basicInformation',
        );
        if (!getCurrentConfig.ok()) {
            throw new Error(`Failed to get system config: ${getCurrentConfig.status()} ${getCurrentConfig.statusText()}`);
        }
        originalConfig = await getCurrentConfig.json();

        // Merge in the googleReCaptchaV2 settings
        const updatedConfig = {
            null: {
                ...(originalConfig as Record<string, unknown>),
                'core.basicInformation.activeCaptchasV2': {
                    ...((originalConfig['core.basicInformation.activeCaptchasV2'] as Record<string, unknown>) || {}),
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
            },
        };

        // Post the complete merged config
        const updateResponse = await TestDataService.AdminApiClient.post('_action/system-config/batch', {
            data: updatedConfig,
        });
        if (!updateResponse.ok()) {
            throw new Error(`Failed to update system config: ${updateResponse.status()} ${updateResponse.statusText()}`);
        }

        await TestDataService.clearCaches();
    });

    test.afterEach(async ({ TestDataService }) => {
        // Restore original config
        if (Object.keys(originalConfig).length > 0) {
            await TestDataService.AdminApiClient.post('_action/system-config/batch', {
                data: { null: originalConfig },
            });

            await TestDataService.clearCaches();
        }
    });

    test(
        'As a user, I can perform a registration by validating to be not a robot via the invisible Google reCaptcha V2.',
        {
            tag: [
                '@Form',
                '@Registration',
                '@Captcha',
                '@Frontend',
            ],
        },
        async ({
            ShopCustomer,
            FrontendAccountLogin,
            FrontendAccount,
            IdProvider,
            Register,
            acceptTechnicalRequiredCookies,
        }) => {
            const user = { email: `${IdProvider.getIdPair().uuid}@test.com` };

            await test.step('Navigate to login page and verify initial state', async () => {
                await ShopCustomer.goesTo(FrontendAccountLogin.url());
                await verifyRecaptchaScriptNotLoaded(FrontendAccountLogin.page, test, 'V2');
            });

            await test.step('Accept cookies and verify _GRECAPTCHA cookie is set', async () => {
                await acceptTechnicalRequiredCookies();

                // Wait for _GRECAPTCHA cookie to be set (with retry to handle config propagation delay)
                await ShopCustomer.expects(async () => {
                    const cookies = await FrontendAccountLogin.page.context().cookies();
                    const grecaptchaCookie = cookies.find((c) => c.name === '_GRECAPTCHA');
                    await ShopCustomer.expects(grecaptchaCookie).toBeTruthy();
                }).toPass({
                    intervals: [
                        1_000,
                        2_500,
                    ],
                });

                await waitForRecaptchaScriptLoaded(FrontendAccountLogin.page);
                await verifyRecaptchaProtectionNotice(FrontendAccountLogin.page, test, 'V2');
            });

            await test.step('User attempts to register and is automatically validated via the invisible reCaptcha V2', async () => {
                await ShopCustomer.attemptsTo(Register(user));
                await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
            });
        },
    );
});
