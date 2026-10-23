import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Visual: Frontend Blog Detail Page',
    { tag: '@Visual' },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, CheckVisibilityInHome }) => {
        const blog = await TestDataService.createBasicProduct({
            name: 'TestProduct',
            blogNumber: '123',
        });
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot of the blog detail page General tab.', async () => {
            await CheckVisibilityInHome(blog.name);
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            ShopCustomer.expects(FrontendProductDetail.addToCartButton).toBeVisible();
            await expect(FrontendProductDetail.page).toHaveScreenshot('Blog-Detail-Page.png', {
                fullPage: true,
            });
        });
    },
);

test(
    'Visual: Frontend Blog Detail Review',
    { tag: '@Visual' },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, CheckVisibilityInHome }) => {
        const blog = await TestDataService.createBasicProduct({
            name: 'TestProduct',
            blogNumber: '123',
        });

        await TestDataService.createProductReview(blog.id, {
            title: 'Very Good',
            points: 4,
            createdAt: '2025-01-01T12:00:00.213+00:00',
        });
        await TestDataService.createProductReview(blog.id, {
            title: 'Excellent',
            points: 5,
            createdAt: '2025-01-02T13:00:00.213+00:00',
        });
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot of the blog detail page reviews tab.', async () => {
            await CheckVisibilityInHome(blog.name);
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await FrontendProductDetail.reviewsTab.click();

            await ShopCustomer.expects(FrontendProductDetail.blogReviewRating).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.blogReviewsLink).toHaveText('2 Reviews');
            await ShopCustomer.expects(FrontendProductDetail.reviewCounter).toContainText('2 reviews');
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(2);

            await expect(FrontendProductDetail.page).toHaveScreenshot('Blog-Detail-Review.png', {
                fullPage: true,
            });
        });
    },
);
