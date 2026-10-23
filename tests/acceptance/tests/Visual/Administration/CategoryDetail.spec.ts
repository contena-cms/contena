import { test, setViewport, replaceElements, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Visual: Administration category page', { tag: '@Visual' }, async ({ ShopAdmin, AdminCategories }) => {
    await test.step('Creates a screenshot of the category page on the general tab.', async () => {
        await ShopAdmin.goesTo(AdminCategories.url());
        await AdminCategories.categoryItems.first().click();
        await setViewport(AdminCategories.page, {
            waitForSelector: '.sw-category-detail-base__description',
        });
        await replaceElements(AdminCategories.page, [AdminCategories.categoryItems]);
        await assertScreenshot(AdminCategories.page, 'Category-General.png');
    });

    await test.step('Creates a screenshot of the "configure home page" modal.', async () => {
        await AdminCategories.configureHomePageButton.click();
        await setViewport(AdminCategories.page, {
            waitForSelector: '.sw-category-entry-point-modal__seo-headline',
        });
        await assertScreenshot(
            AdminCategories.page,
            'Category-Modal.png',
            AdminCategories.page.locator('.sw-modal__dialog'),
        );
        await AdminCategories.configureModalCancelButton.click();
    });

    await test.step('Creates a screenshot of the category page on the blogs tab.', async () => {
        await AdminCategories.blogsTab.click();
        await setViewport(AdminCategories.page, {
            width: 1440,
            waitForSelector: '.sw-category-detail-blogs__product-assignment-type-select',
        });
        await replaceElements(AdminCategories.page, [AdminCategories.categoryItems]);
        await assertScreenshot(AdminCategories.page, 'Category-Blogs.png');
    });

    await test.step('Creates a screenshot of the category page on the layout tab.', async () => {
        await AdminCategories.layoutTab.click();
        await setViewport(AdminCategories.page, {
            waitForSelector: '.sw-cms-el-config-blog-listing__content-info',
        });
        await replaceElements(AdminCategories.page, [AdminCategories.categoryItems]);
        await assertScreenshot(AdminCategories.page, 'Category-Layout.png');
    });

    await test.step('Creates a screenshot of the category page on the SEO tab.', async () => {
        await AdminCategories.seoTab.click();
        await setViewport(AdminCategories.page, {
            waitForSelector: '.sw-seo-url__card',
        });
        await replaceElements(AdminCategories.page, [AdminCategories.categoryItems]);
        await assertScreenshot(AdminCategories.page, 'Category-SEO.png');
    });
});
