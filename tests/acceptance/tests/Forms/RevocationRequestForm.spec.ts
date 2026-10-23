import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to switch on and off the revocation request form',
    {
        tag: [
            '@Form',
            '@Revocation',
            '@Frontend',
        ],
    },
    async ({
        AddProductToCart,
        ShopCustomer,
        FrontendCheckoutRegister,
        FrontendHome,
        FrontendProductDetail,
        TestDataService,
    }) => {
        const revocationButtonName = /Revoke a contract|Vertrag widerrufen/i;
        const revocationFormButton = () => {
            return FrontendHome.page.getByRole('link', { name: revocationButtonName });
        };
        const minimalFooterRevocationButton = () => {
            return FrontendCheckoutRegister.page
                .locator('.footer-minimal .footer-revocation-button')
                .getByRole('link', { name: revocationButtonName });
        };
        const revocationForm = () => FrontendHome.page.locator('#cms-form-online-revocation-request');
        const revocationPrivacyNotice = () => revocationForm().locator('.data-protection-information');
        const revocationPrivacyNoticeLink = () => revocationPrivacyNotice().getByRole('button', { name: /here|hier/i });

        const openFrontendHome = async () => {
            await ShopCustomer.goesTo(`${FrontendHome.url()}?a=${Date.now()}`);
        };

        await test.step('Visit the home page to check there is no revocation button', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': false });

            await ShopCustomer.expects(async () => {
                await openFrontendHome();
                await expect(revocationFormButton()).toBeHidden();
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ],
            });
        });

        await test.step('Enables the revocation button and check if it is visible', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.showRevocationButton': true });

            await ShopCustomer.expects(async () => {
                await openFrontendHome();
                await expect(revocationFormButton()).toBeVisible();
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ],
            });
        });

        await test.step('Shows a non-blocking privacy notice in the revocation form', async () => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.showRevocationButton': true,
                'core.basicInformation.useDefaultCookieConsent': false,
                'core.loginRegistration.requireDataProtectionCheckbox': true,
            });

            await ShopCustomer.expects(async () => {
                await openFrontendHome();
                await revocationFormButton().click();

                await expect(revocationForm()).toBeVisible();
                await expect(revocationForm().locator('input[name="acceptedDataProtection"]')).toHaveCount(0);
                await expect(revocationPrivacyNotice()).toContainText(/Privacy Notice|Datenschutzhinweise/i);
                await expect(revocationPrivacyNoticeLink()).toBeVisible();
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ],
            });

            await revocationPrivacyNoticeLink().click();
            await expect(FrontendHome.page.locator('.modal.show')).toBeVisible();
        });

        await test.step('Check if the revocation button is visible without opening the footer column on mobile', async () => {
            await TestDataService.setSystemConfig({
                'core.basicInformation.showRevocationButton': true,
                'core.basicInformation.useDefaultCookieConsent': false,
                'core.loginRegistration.requireDataProtectionCheckbox': false,
            });

            await FrontendHome.page.setViewportSize({ width: 390, height: 844 });

            await ShopCustomer.expects(async () => {
                await openFrontendHome();
                await FrontendHome.page.locator('.footer-main').scrollIntoViewIfNeeded();

                const collapsedHotlineContent = FrontendHome.page.locator('#collapseFooterHotline');

                await expect(collapsedHotlineContent).toBeHidden();
                await expect(collapsedHotlineContent.getByRole('link', { name: revocationButtonName })).toHaveCount(0);
                await expect(revocationFormButton()).toBeVisible();
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ],
            });
        });

        await test.step('Check if the revocation button is visible in the minimal footer', async () => {
            const blog = await TestDataService.createBasicProduct();

            await TestDataService.setSystemConfig({
                'core.basicInformation.showRevocationButton': true,
                'core.basicInformation.useDefaultCookieConsent': false,
                'core.loginRegistration.requireDataProtectionCheckbox': false,
            });

            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.attemptsTo(AddProductToCart(blog));

            for (const viewport of [
                { width: 390, height: 844 },
                { width: 1280, height: 720 },
            ]) {
                await FrontendCheckoutRegister.page.setViewportSize(viewport);

                await ShopCustomer.expects(async () => {
                    await ShopCustomer.goesTo(`${FrontendCheckoutRegister.url()}?a=${Date.now()}`);
                    await FrontendCheckoutRegister.page.locator('.footer-minimal').scrollIntoViewIfNeeded();

                    await expect(minimalFooterRevocationButton()).toBeVisible();
                }).toPass({
                    intervals: [
                        1_000,
                        2_500,
                    ],
                });
            }
        });
    },
);
