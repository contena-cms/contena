import { test } from '@fixtures/AcceptanceTest';

test(
    'User is able to search blogs in shop',
    {
        tag: [
            '@Search',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendHome, FrontendSearchSuggest, SearchForTerm, IdProvider }) => {
        const blogNameSuffix1 = IdProvider.getIdPair().uuid;
        const createBottle = TestDataService.createBasicProduct({
            name: `Bottle ${blogNameSuffix1}`,
        });
        const createBowl = TestDataService.createBasicProduct({
            name: `Bowl ${blogNameSuffix1}`,
        });

        await Promise.all([
            createBottle,
            createBowl,
        ]);

        await TestDataService.clearCaches();

        await test.step('Blogs are visible.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            const blogLocator1 = await FrontendHome.getListingItemByProductName(`Bottle ${blogNameSuffix1}`);
            await ShopCustomer.expects(blogLocator1.blogName).toBeVisible();
            const blogLocator2 = await FrontendHome.getListingItemByProductName(`Bowl ${blogNameSuffix1}`);
            await ShopCustomer.expects(blogLocator2.blogName).toBeVisible();
        });

        await test.step('User searches with an invalid input and sees no results', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            await ShopCustomer.attemptsTo(SearchForTerm('thisShouldNotFindAnything'));
            await ShopCustomer.expects(FrontendSearchSuggest.searchSuggestNoResult).toBeVisible();
        });

        await test.step('User searches term and sees a single matching blog', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm('Bottle'));
            const totalCount1 = await FrontendSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount1).toBe(1);

            const lineItemText = await FrontendSearchSuggest.searchSuggestLineItemName.first().textContent();
            ShopCustomer.expects(lineItemText).toContain(`Bottle ${blogNameSuffix1}`);
        });

        await test.step('User searches for a partial term and sees multiple matching blogs', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(blogNameSuffix1));
            const totalCount2 = await FrontendSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount2).toBe(2);
        });

        await test.step('User navigates to the results page to view all matching blogs', async () => {
            await ShopCustomer.presses(FrontendSearchSuggest.searchSuggestTotalLink);
            await ShopCustomer.expects(FrontendSearchSuggest.searchHeadline).toContainText(blogNameSuffix1);
            await ShopCustomer.expects(FrontendSearchSuggest.blogListItems).toHaveCount(2);
        });
    },
);
