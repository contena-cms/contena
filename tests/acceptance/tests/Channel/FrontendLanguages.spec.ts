import { formatPrice, getLanguageData, getSnippetSetId, test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Shop users should be able to view blogs in different languages.',
    {
        tag: [
            '@Languages',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendHeader, FrontendHome, InstanceMeta }) => {
        const blog = await TestDataService.createBasicProduct();

        const channelId = TestDataService.defaultSalesChannel.id;
        const language = await getLanguageData(TestDataService.AdminApiClient, 'de-DE');
        const snippetSetId = await getSnippetSetId(TestDataService.AdminApiClient, 'de-DE');
        const germanDomainUrl = `${(process.env.APP_URL || 'http://localhost:8000').replace(/\/$/, '')}/de-DE/`;

        await TestDataService.assignSalesChannelLanguage(channelId, language.id);
        await TestDataService.createSalesChannelDomain({
            languageId: language.id,
            snippetSetId: snippetSetId,
            url: germanDomainUrl,
        });

        await TestDataService.clearCaches();

        const blogListing = FrontendHome.blogListItems.filter({
            has: FrontendHome.page.getByRole('link', { name: blog.name }),
        });
        const addToCartButton = blogListing.filter({ has: FrontendHome.page.getByRole('button') });
        const languageDropdown = FrontendHome.page.locator('#languagesDropdown-top-bar');

        await ShopCustomer.expects(async () => {
            await test.step('User can view languages menu', async () => {
                await ShopCustomer.goesTo(germanDomainUrl);
                await ShopCustomer.expects(languageDropdown).toContainText('Deutsch');
                await ShopCustomer.expects(addToCartButton).toContainText('In den Warenkorb');
            });
        }).toPass({
            intervals: [
                1_000,
                2_500,
            ], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('User can select a different language', async () => {
            await ShopCustomer.presses(languageDropdown);
            // Select the second li.top-bar-list-item (index 1) and click the button inside it
            // covers both cases: with and without feature flag v6.8.0 and English and English (United Kingdom)

            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
                await FrontendHeader.page.locator('.top-bar-language').getByRole('list').getByText('English').click();
            } else {
                const secondListItem = FrontendHome.page.locator('li.top-bar-list-item').nth(1);
                await ShopCustomer.presses(secondListItem.locator('button.dropdown-item'));
            }

            await ShopCustomer.expects(languageDropdown).toContainText('English');
            await ShopCustomer.expects(addToCartButton).toContainText('Add to shopping cart');
        });
    },
);
