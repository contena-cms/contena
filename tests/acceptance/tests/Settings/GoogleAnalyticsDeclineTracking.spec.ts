import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop user, I want to accept only the technically required cookies without activating Google Analytics tracking via the basic cookie consent banner in the frontend.',
    {
        tag: [
            '@Settings',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, TestDataService, DefaultSalesChannel }) => {
        await test.step('Configure sales channel analytics and verify cookie banner visibility', async () => {
            const channelAnalytics = await TestDataService.createSalesChannelAnalytics();
            await TestDataService.assignSalesChannelAnalytics(DefaultSalesChannel.channel.id, channelAnalytics.id);

            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible();
        });

        await test.step('Verify default cookie consent settings', async () => {
            await ShopCustomer.presses(FrontendHome.consentConfigureButton);
            await ShopCustomer.expects(FrontendHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
            await ShopCustomer.expects(FrontendHome.consentDialogStatisticsCheckbox).not.toBeChecked();
            await ShopCustomer.expects(FrontendHome.consentDialogMarketingdCheckbox).not.toBeChecked();
            await ShopCustomer.expects(FrontendHome.consentDialog.getByRole('switch')).toHaveCount(4);
            await ShopCustomer.presses(FrontendHome.consentDialogSaveButton);

            // Wait for banner to disappear after saving
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible();
        });

        await test.step('Verify cookies after saving default consent settings', async () => {
            // Poll for cookie preference to be set (handles async cookie setting)
            await ShopCustomer.expects
                .poll(async () => {
                    const cookies = await FrontendHome.page.context().cookies();
                    return cookies.find((c) => c.name === 'cookie-preference')?.value;
                })
                .toBe('1');

            const allCookies = await FrontendHome.page.context().cookies();
            // Verify analytics cookies are NOT set (admin_user declined)
            ShopCustomer.expects(allCookies.find((c) => c.name === 'google-analytics-enabled')).not.toBeDefined();
            ShopCustomer.expects(allCookies.find((c) => c.name === 'google-ads-enabled')).not.toBeDefined();

            // Verify cookie preference is set (admin_user made a choice)
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');
        });

        await test.step('Verify cookies persist after page reload', async () => {
            await FrontendHome.page.reload();
            const allCookies = await FrontendHome.page.context().cookies();
            // Verify analytics cookies remain NOT set after reload
            ShopCustomer.expects(allCookies.find((c) => c.name === 'google-analytics-enabled')).not.toBeDefined();
            ShopCustomer.expects(allCookies.find((c) => c.name === 'google-ads-enabled')).not.toBeDefined();

            // Verify cookie preference persists
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');

            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible();
        });
    },
);
