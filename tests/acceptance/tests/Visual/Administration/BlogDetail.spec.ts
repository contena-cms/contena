import { test, setViewport, replaceElements, assertScreenshot, getCurrencyCodeFromLocale } from '@fixtures/AcceptanceTest';

test(
    'Visual: Blog Detail Page',
    { tag: '@Visual' },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, ChannelBaseConfig }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());

        const blog = await TestDataService.createBasicProduct({
            name: 'Test Blog',
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

        await test.step('Creates a screenshot of the blog detail page General tab.', async () => {
            await ShopAdmin.goesTo(AdminProductDetail.url(blog.id));
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.releaseDateInput,
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
                AdminProductDetail.page.locator('.sw-select-selection-list__item'),
                AdminProductDetail.page.locator('.sw-category-tree-field__label-property'),
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-General-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page Specifications tab.', async () => {
            await AdminProductDetail.specificationsTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.getByRole('button', {
                    name: 'Configure properties',
                }),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-Specifications-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page Advanced Pricing tab.', async () => {
            await AdminProductDetail.advancedPricingTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.locator('.sw-blog-detail-context-prices__empty-state'),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-Advanced-Pricing-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page Variants tab.', async () => {
            await AdminProductDetail.variantsTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.getByRole('button', {
                    name: 'Configure properties',
                }),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-Variants-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page Layout tab.', async () => {
            await AdminProductDetail.layoutTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.getByRole('button', {
                    name: 'Change layout',
                }),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-Layout-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page SEO tab.', async () => {
            await AdminProductDetail.SEOTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.getByRole('button', {
                    name: 'Upload file',
                }),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-SEO-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page Cross Selling tab.', async () => {
            await AdminProductDetail.crossSellingTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.getByRole('button', {
                    name: 'Add new Cross Selling',
                }),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-Cross-Selling-Tab.png');
        });

        await test.step('Creates a screenshot of the blog detail page Reviews tab.', async () => {
            await AdminProductDetail.reviewsTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.getByRole('button', {
                    name: 'Go to reviews',
                }),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.blogHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Blog-Detail-Reviews-Tab.png');
        });
    },
);
