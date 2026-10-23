import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop user, I want to accept Google Analytics tracking via the basic cookie consent banner in the frontend.',
    {
        tag: [
            '@Settings',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, TestDataService, DefaultSalesChannel }) => {
        await test.step('Configure sales channel analytics and verify cookie banner visibility on the home page', async () => {
            const channelAnalytics = await TestDataService.createSalesChannelAnalytics();
            await TestDataService.assignSalesChannelAnalytics(DefaultSalesChannel.channel.id, channelAnalytics.id);

            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible();
        });

        await test.step('Enable Google Analytics and marketing tracking via cookie settings', async () => {
            await ShopCustomer.presses(FrontendHome.consentConfigureButton);
            await ShopCustomer.presses(FrontendHome.consentDialogStatisticsCheckbox);
            await ShopCustomer.presses(FrontendHome.consentDialogMarketingdCheckbox);
            await ShopCustomer.presses(FrontendHome.consentDialogSaveButton);

            // Wait for banner to disappear after saving, which indicates cookies are processed
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible();
        });

        await test.step('Verify tracking cookies are set correctly after consent', async () => {
            // Poll for cookie preference to be set (handles async cookie setting)
            await ShopCustomer.expects
                .poll(async () => {
                    const cookies = await FrontendHome.page.context().cookies();
                    return cookies.find((c) => c.name === 'cookie-preference')?.value;
                })
                .toBe('1');

            const allCookies = await FrontendHome.page.context().cookies();
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference).toBeDefined();
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');

            // Verify analytics cookies if they exist (they may be set by client-side JS)
            const googleAnalyticsCookie = allCookies.find((c) => c.name === 'google-analytics-enabled');
            const googleAdsCookie = allCookies.find((c) => c.name === 'google-ads-enabled');
            if (googleAnalyticsCookie) {
                ShopCustomer.expects(googleAnalyticsCookie.value).toEqual('1');
            }
            if (googleAdsCookie) {
                ShopCustomer.expects(googleAdsCookie.value).toEqual('1');
            }
        });

        await test.step('Verify tracking cookies persist after page reload', async () => {
            await FrontendHome.page.reload({ waitUntil: 'commit' });

            const allCookies = await FrontendHome.page.context().cookies();

            // Verify cookie preference persists
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference).toBeDefined();
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');

            // Verify analytics cookies persist if they were set
            const googleAnalyticsCookie = allCookies.find((c) => c.name === 'google-analytics-enabled');
            const googleAdsCookie = allCookies.find((c) => c.name === 'google-ads-enabled');
            if (googleAnalyticsCookie) {
                ShopCustomer.expects(googleAnalyticsCookie.value).toEqual('1');
            }
            if (googleAdsCookie) {
                ShopCustomer.expects(googleAdsCookie.value).toEqual('1');
            }

            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible();
        });
    },
);
