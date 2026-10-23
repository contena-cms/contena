import { test, assertScreenshot, setViewport, hideElements } from '@fixtures/AcceptanceTest';

test(
    'Visual: Administration sales channels page',
    {
        tag: '@Visual',
    },
    async ({ ShopAdmin, AdminSalesChannelDetail, DefaultSalesChannel }) => {
        await test.step('Creates a screenshot of the sales channel page general tab.', async () => {
            await ShopAdmin.goesTo(AdminSalesChannelDetail.url(DefaultSalesChannel.channel.id));
            await setViewport(AdminSalesChannelDetail.page, {
                waitForSelector: '.sw-sales-channel-detail-base',
            });
            await hideElements(AdminSalesChannelDetail.page, [
                'td.sw-data-grid__cell--url .sw-data-grid__cell-content',
                'input[aria-label="API access key"]',
            ]);
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-General-Tab.png');
        });

        await test.step('Creates a screenshot of the sales channel page blogs tab.', async () => {
            await AdminSalesChannelDetail.blogsTabLink.click();
            await setViewport(AdminSalesChannelDetail.page, {
                waitForSelector: '.mt-card sw-sales-channel-detail-blogs',
            });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Blogs-Tab.png');
        });

        await test.step('Creates a screenshot of the sales channel page theme tab.', async () => {
            await AdminSalesChannelDetail.themeTabLink.click();
            await setViewport(AdminSalesChannelDetail.page, {
                waitForSelector: '.sw-tabs sw-tabs--small sw-sales-channel-detail-__tabs',
            });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Theme-Tab.png');
        });

        await test.step('Creates a screenshot of the sales channel page analytics tab.', async () => {
            await AdminSalesChannelDetail.analyticsTabLink.click();
            await setViewport(AdminSalesChannelDetail.page, {
                waitForSelector: '.sw-tabs sw-tabs--small sw-sales-channel-detail-__tabs',
            });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Analytics-Tab.png');
        });
    },
);
