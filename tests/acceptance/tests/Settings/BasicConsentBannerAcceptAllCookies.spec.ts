import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop user, I want to use an "Accept All Cookies" button in the basic cookie consent banner in the frontend.',
    {
        tag: [
            '@Settings',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, TestDataService }) => {
        const COOKIE_BANNER_VISIBILITY_TIMEOUT = 2_000;

        await test.step('Enable "Accept All Cookies" button in system configuration', async () => {
            await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
        });

        await test.step('Navigate to the homepage and verify cookie consent banner', async () => {
            await ShopCustomer.expects(async () => {
                await ShopCustomer.goesTo(FrontendHome.url());
                await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible({
                    timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
                });
                await ShopCustomer.expects(FrontendHome.consentAcceptAllCookiesButton).toBeVisible({
                    timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
                });
            }).toPass({ intervals: [500], timeout: 20000 });
        });

        await test.step('Click "Accept All Cookies" and verify cookies are accepted', async () => {
            await ShopCustomer.presses(FrontendHome.consentAcceptAllCookiesButton);

            // Wait for banner to disappear, which indicates cookies have been set
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible({
                timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
            });

            const allCookies = await FrontendHome.page.context().cookies();
            // Verify essential cookies are set
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference).toBeDefined();
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');
        });

        await test.step('Reload page and verify cookie persistence', async () => {
            await FrontendHome.page.reload();
            const allCookies = await FrontendHome.page.context().cookies();
            // Verify cookie preference persists
            const cookiePreference = allCookies.find((c) => c.name === 'cookie-preference');
            ShopCustomer.expects(cookiePreference).toBeDefined();
            ShopCustomer.expects(cookiePreference?.value).toEqual('1');
        });

        await test.step('Verify cookie consent banner is no longer visible', async () => {
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).not.toBeVisible({
                timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
            });
        });
    },
);
