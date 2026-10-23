import { getCurrencySymbolFromLocale, test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Shop users should be able to view blogs in different currencies.',
    {
        tag: [
            '@Currencies',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, InstanceMeta, FrontendHeader, FrontendHome, ChangeFrontendCurrency }) => {
        const channelId = TestDataService.defaultSalesChannel.id;
        const currency = await TestDataService.createCurrency();
        await TestDataService.assignSalesChannelCurrency(channelId, currency.id);
        const blog = await TestDataService.createBasicProduct();
        const blogListing = await FrontendHome.getListingItemByProductName(blog.name);

        await ShopCustomer.expects(async () => {
            await test.step('User can view currencies menu', async () => {
                await ShopCustomer.goesTo(FrontendHome.url());
                const currencySymbol = getCurrencySymbolFromLocale();
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (satisfies(InstanceMeta.version, '<6.7')) {
                    await ShopCustomer.expects(FrontendHeader.currenciesDropdown).toContainText('Pound');
                } else {
                    await ShopCustomer.expects(FrontendHeader.currenciesDropdown).toContainText(currencySymbol);
                }
                await ShopCustomer.expects(blogListing.blogPrice).toContainText(currencySymbol);
            });
        }).toPass({
            intervals: [
                1_000,
                2_500,
            ], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('User can select a different currency', async () => {
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
                await FrontendHeader.currenciesDropdown.click();
                await FrontendHeader.currenciesMenuOptions.getByText(currency.symbol).click();
            } else {
                await ShopCustomer.attemptsTo(ChangeFrontendCurrency(currency.name));
            }
            await ShopCustomer.expects(FrontendHeader.currenciesDropdown).toContainText(currency.name);
            await ShopCustomer.expects(blogListing.blogPrice).toContainText(currency.isoCode);
        });
    },
);
