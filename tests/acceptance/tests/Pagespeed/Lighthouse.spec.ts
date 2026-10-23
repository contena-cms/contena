import { test } from '@fixtures/AcceptanceTest';

test.skip(
    process.env.GITHUB_REPOSITORY === 'contena/contena-private',
    'Lighthouse tests require the performance characteristics of the public repository runners.',
);

/**
 * These tests should only run against APP_ENV=Prod
 */
test('Blog Detail Lighthouse Report', async ({
    ShopCustomer,
    TestDataService,
    ValidateLighthouseScore,
    FrontendProductDetail,
}) => {
    const blog = await TestDataService.createProductWithImage();

    await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
    await ShopCustomer.attemptsTo(ValidateLighthouseScore(FrontendProductDetail.page, 'Frontend-Blog-Detail'));
});

test('Category Lighthouse Report', async ({
    ShopCustomer,
    TestDataService,
    ValidateLighthouseScore,
    FrontendCategory,
    InstanceMeta,
}) => {
    test.setTimeout(150_000);

    const blogCount = 10;

    const category = await TestDataService.createCategory();

    for (let i = 0; i < blogCount; i++) {
        const blog = await TestDataService.createProductWithImage();
        await TestDataService.assignProductCategory(blog.id, category.id);
    }

    await ShopCustomer.goesTo(FrontendCategory.url(category.name));

    await ShopCustomer.expects(async () => {
        if (InstanceMeta.isSaaS || InstanceMeta.isPaaS) {
            await TestDataService.clearCaches();
        }
        await ShopCustomer.goesTo(`${FrontendCategory.url(category.name)}?a=${Date.now()}`);
        await ShopCustomer.expects(FrontendCategory.page.locator('.cms-listing-row').locator('.blog-name')).toHaveCount(
            blogCount,
        );
    }).toPass({
        intervals: [
            1_000,
            2_500,
        ], // retry after 1 seconds, then every 2.5 seconds
    });

    await ShopCustomer.attemptsTo(ValidateLighthouseScore(FrontendCategory.page, 'Frontend-Category'));
});

test('Cart Lighthouse Report', async ({
    ShopCustomer,
    TestDataService,
    ValidateLighthouseScore,
    Login,
    AddProductToCart,
    FrontendProductDetail,
    FrontendCheckoutCart,
}) => {
    const blog = await TestDataService.createProductWithImage();

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
    await ShopCustomer.attemptsTo(AddProductToCart(blog, '5'));

    await ShopCustomer.goesTo(FrontendCheckoutCart.url());
    await ShopCustomer.attemptsTo(ValidateLighthouseScore(FrontendCheckoutCart.page, 'Frontend-Cart'));
});
