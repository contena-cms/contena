import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop user, I want to continue shopping without accepting the cookies in the frontend.',
    {
        tag: [
            '@Settings',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendHome, TestDataService, CheckVisibilityInHome }) => {
        const COOKIE_BANNER_VISIBILITY_TIMEOUT = 15_000;

        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
        const blog = await TestDataService.createBasicProduct();
        const category = await TestDataService.createCategory();
        await TestDataService.assignProductCategory(blog.id, category.id);

        await TestDataService.clearCaches();

        await test.step('Navigate to homepage and verify cookie banner', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await CheckVisibilityInHome(blog.name)();
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible({
                timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
            });
            await ShopCustomer.expects(FrontendHome.consentAcceptAllCookiesButton).toBeVisible({
                timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
            });
        });

        await test.step('Dismiss cookie banner using the configure option without choosing a preference, cookie banner should be displayed again', async () => {
            await ShopCustomer.presses(FrontendHome.consentConfigureButton);
            await ShopCustomer.presses(FrontendHome.consentDialogCloseButton);
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible({
                timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
            });
        });

        await test.step('Verify cookies after dismissing the cookie banner', async () => {
            const allCookies = await FrontendHome.page.context().cookies();
            ShopCustomer.expects(allCookies.length).toEqual(2);
        });

        await test.step('Navigate to the blog page and verify the cookie banner', async () => {
            const blogListItemLocators = await FrontendHome.getListingItemByProductName(blog.name);
            await ShopCustomer.presses(blogListItemLocators.blogName);
            await FrontendHome.page.reload();
            await ShopCustomer.expects(FrontendHome.consentCookieBannerContainer).toBeVisible({
                timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT,
            });
        });
    },
);
