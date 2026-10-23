import { test } from '@fixtures/AcceptanceTest';
import { Blog } from '@contena-ag/acceptance-test-suite';

test(
    'Blog is visible in listing and frontend search when set to "Visible".',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        SearchForTerm,
        FrontendSearchSuggest,
        IdProvider,
        FrontendProductDetail,
        CheckVisibilityInHome,
    }) => {
        let blog: Blog;
        await test.step('Create a blog with "Visible" visibility in the default sales channel.', async () => {
            blog = await TestDataService.createBasicProduct({
                name: 'Blog-' + (await IdProvider.getIdPair().id),
                visibilities: [
                    {
                        channelId: DefaultSalesChannel.channel.id,
                        visibility: 30,
                    },
                ],
            });
        });

        await TestDataService.clearCaches();

        await CheckVisibilityInHome(blog.name)();

        await test.step('Verify the blog appears in frontend search results.', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(blog.name));
            const totalCount1 = await FrontendSearchSuggest.getTotalSearchResultCount();

            // if we create other blogs in parallel - for example by using workers - we might find multiple results
            await ShopCustomer.expects(totalCount1).toBeGreaterThanOrEqual(1);
        });

        await test.step('Verify the blog can be accessed directly via its URL.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.expects(FrontendProductDetail.page.locator('h1')).toContainText(blog.name);
        });
    },
);

test(
    'Blog is visible in frontend search but hidden from listing when set to "Hide in listings".',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendHome,
        DefaultSalesChannel,
        SearchForTerm,
        FrontendSearchSuggest,
        IdProvider,
        FrontendProductDetail,
    }) => {
        let blog: Blog;
        await test.step('Create a blog with "Hide in listings" visibility in the default sales channel.', async () => {
            blog = await TestDataService.createBasicProduct({
                name: 'Blog-' + IdProvider.getIdPair().id,
                visibilities: [
                    {
                        channelId: DefaultSalesChannel.channel.id,
                        visibility: 20,
                    },
                ],
            });
        });

        await test.step('Verify the blog does not appear in the Home category listing.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            const blogLocators = await FrontendHome.getListingItemByProductName(blog.name);
            await ShopCustomer.expects(blogLocators.blogName).not.toBeVisible();
        });

        await ShopCustomer.expects(async () => {
            await test.step('Verify the blog appears in frontend search results.', async () => {
                await ShopCustomer.attemptsTo(SearchForTerm(blog.name));
                await ShopCustomer.expects(
                    FrontendSearchSuggest.searchSuggestLineItemName.getByText(blog.name),
                ).toBeVisible();
                const totalCount1 = await FrontendSearchSuggest.getTotalSearchResultCount();

                // if we create other blogs in parallel - for example by using workers - we might find multiple results
                await ShopCustomer.expects(totalCount1).toBeGreaterThanOrEqual(1);
                await ShopCustomer.expects(
                    FrontendSearchSuggest.searchSuggestLineItemName.getByText(blog.name),
                ).toBeVisible();
            });
        }).toPass({
            intervals: [
                1_000,
                2_500,
            ], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('Verify the blog can be accessed directly via its URL.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.expects(FrontendProductDetail.page.locator('h1')).toContainText(blog.name);
        });
    },
);

test(
    'Blog is hidden from both listing and frontend search when set to "Hide in listings and search".',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendHome,
        DefaultSalesChannel,
        SearchForTerm,
        FrontendSearchSuggest,
        IdProvider,
        FrontendProductDetail,
    }) => {
        let blog: Blog;
        await test.step('Create a blog with "Hide in listings and search" visibility in the default sales channel.', async () => {
            blog = await TestDataService.createBasicProduct({
                name: 'Blog-' + IdProvider.getIdPair().id,
                visibilities: [
                    {
                        channelId: DefaultSalesChannel.channel.id,
                        visibility: 10,
                    },
                ],
            });
        });

        await test.step('Verify the blog does not appear in the Home category listing.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            const blogLocators = await FrontendHome.getListingItemByProductName(blog.name);
            await ShopCustomer.expects(blogLocators.blogName).not.toBeVisible();
        });

        await test.step('Verify the blog does not appear in frontend search results.', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(blog.name));
            // if we have other test blogs the result might not be empty but our blog should not be in the list
            await ShopCustomer.expects(
                FrontendSearchSuggest.searchSuggestLineItemName.getByText(blog.name),
            ).not.toBeVisible();
        });

        await test.step('Verify the blog can still be accessed directly via its URL.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.expects(FrontendProductDetail.page.locator('h1')).toContainText(blog.name);
        });
    },
);

test(
    'Blog is not visible without adding it to the sales channel.',
    {
        tag: [
            '@Blog',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendHome,
        SearchForTerm,
        FrontendSearchSuggest,
        IdProvider,
        FrontendProductDetail,
    }) => {
        let blog: Blog;
        await test.step('Create a blog without visibility in the default sales channel.', async () => {
            blog = await TestDataService.createBasicProduct({
                name: 'Blog-' + (await IdProvider.getIdPair().id),
                visibilities: [],
            });
        });

        await test.step('Verify the blog does not appear in the Home category listing.', async () => {
            await ShopCustomer.goesTo(FrontendHome.url());
            const blogLocators = await FrontendHome.getListingItemByProductName(blog.name);
            await ShopCustomer.expects(blogLocators.blogName).not.toBeVisible();
        });

        await test.step('Verify the blog does not appear in frontend search results.', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(blog.name));
            // if we have other test blogs the result might not be empty but our blog should not be in the list
            await ShopCustomer.expects(
                FrontendSearchSuggest.searchSuggestLineItemName.getByText(blog.name),
            ).not.toBeVisible();
        });

        await test.step('Verify the blog can still be accessed directly via its URL.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.expects(
                FrontendProductDetail.page.locator('h1', { hasText: blog.name }),
            ).not.toBeVisible();
        });
    },
);
