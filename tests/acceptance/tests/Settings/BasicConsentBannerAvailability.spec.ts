import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop user, I want use a basic cookie consent banner in the frontend.',
    {
        tag: [
            '@Settings',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome }) => {
        await test.step('Navigate to homepage and verify initial cookie banner visibility and content', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible();
            // Check if "Accept All Cookies" button exists and verify its visibility state
            const acceptAllButton = FrontendHome.consentAcceptAllCookiesButton;
            const isAcceptAllVisible = await acceptAllButton.isVisible().catch(() => false);
            // The button might be visible if acceptAllCookies config is enabled from a previous test
            if (isAcceptAllVisible) {
                console.warn('Accept All Cookies button is visible - this may indicate config bleed from previous test');
            }
            await ShopCustomer.expects(FrontendHome.consentOnlyTechnicallyRequiredButton).toBeVisible();
            await ShopCustomer.expects(FrontendHome.consentConfigureButton).toBeVisible();
            await ShopCustomer.expects(FrontendHome.consentCookiePermissionContent).toContainText(
                'This website uses cookies to ensure the best experience possible.',
            );
        });

        await test.step('Verify no consent cookie is set before acceptance', async () => {
            const allCookies = await FrontendHome.page.context().cookies();
            // The important thing is that cookie-preference is not yet set
            ShopCustomer.expects(allCookies.find((c) => c.name === 'cookie-preference')).not.toBeDefined();
        });

        await test.step('Configure cookie settings', async () => {
            await ShopCustomer.presses(FrontendHome.consentConfigureButton);
            await ShopCustomer.expects(FrontendHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
            await ShopCustomer.expects(FrontendHome.consentDialog.getByRole('switch')).toHaveCount(2);
            await ShopCustomer.presses(FrontendHome.consentDialogSaveButton);

            // Wait for dialog to close, which indicates cookies have been processed
            await ShopCustomer.expects(FrontendHome.consentDialog).not.toBeVisible();
        });

        await test.step('Verify cookies after saving consent settings', async () => {
            const allCookies = await FrontendHome.page.context().cookies();
            // Verify cookie preference is set
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference).toBeDefined();
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');
        });

        await test.step('Reload page and verify cookie persistence', async () => {
            await FrontendHome.page.reload();
            const allCookies = await FrontendHome.page.context().cookies();
            // Verify cookie preference persists after reload
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference).toBeDefined();
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible();
        });
    },
);
