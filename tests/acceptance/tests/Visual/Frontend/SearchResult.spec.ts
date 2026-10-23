import { test, expect, getCurrencyCodeFromLocale, getLocale } from '@fixtures/AcceptanceTest';

test(
    'Creates a screenshot of the Frontend Search Result Page.',
    { tag: '@Visual' },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendHome,
        SearchForTerm,
        FrontendSearchSuggest,
        CheckVisibilityInHome,
        ChannelBaseConfig,
    }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
        const blog = await TestDataService.createBasicProduct({
            name: 'Test Blog1',
            blogNumber: 'TEST-123',
            description: null,
            stock: 10,
            price: [
                {
                    currencyId: currency.id,
                    gross: 10,
                    linked: false,
                    net: 8.4,
                },
                {
                    currencyId: ChannelBaseConfig.defaultCurrencyId,
                    gross: 10,
                    linked: false,
                    net: 8.4,
                },
            ],
        });
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Search with valid input and sees results and take a screenshot.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await CheckVisibilityInHome(blog.name);
            await ShopCustomer.attemptsTo(SearchForTerm(blog.name));
            await ShopCustomer.expects(FrontendSearchSuggest.searchSuggestTotalLink).toBeVisible();
            await expect(FrontendHome.page).toHaveScreenshot('Search-Result-Dropdown.png', {
                fullPage: true,
            });
        });

        await test.step('Navigate to the results page to and take a screenshot.', async () => {
            await FrontendSearchSuggest.searchSuggestTotalLink.click();
            await ShopCustomer.expects(FrontendSearchSuggest.searchHeadline).toContainText(blog.name);
            await expect(FrontendSearchSuggest.page).toHaveScreenshot('Search-Result-Page.png', {
                fullPage: true,
            });
        });
    },
);
