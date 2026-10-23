import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop user, I want to see reviews of a blog.',
    {
        tag: [
            '@Blog',
            '@Reviews',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail }) => {
        const blogWithRating1 = await TestDataService.createBasicProduct();
        await TestDataService.createProductReview(blogWithRating1.id, {
            points: 3,
        });
        await TestDataService.createProductReview(blogWithRating1.id, {
            points: 4,
        });

        await ShopCustomer.goesTo(FrontendProductDetail.url(blogWithRating1));
        await ShopCustomer.presses(FrontendProductDetail.reviewsTab);

        await ShopCustomer.expects(FrontendProductDetail.blogReviewRating).toBeVisible();
        await ShopCustomer.expects(FrontendProductDetail.blogReviewsLink).toHaveText('2 Reviews');
        await ShopCustomer.expects(FrontendProductDetail.reviewCounter).toContainText('2 reviews');
        await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(2);
    },
);

test(
    'As a shop user, I want to submit a review, so that I can share my experience with the blog',
    {
        tag: [
            '@Blog',
            '@Reviews',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, LoginViaReviewsTab, Logout, InstanceMeta }) => {
        const blog = await TestDataService.createBasicProduct();
        const user = await TestDataService.createCustomer();

        await test.step('Navigate to review tab within blog detail page.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.presses(FrontendProductDetail.reviewsTab);
        });

        await test.step('Validate the empty state of the reviews tab.', async () => {
            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserButton).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(0);
            await ShopCustomer.expects(FrontendProductDetail.reviewEmptyListingText).toContainText(
                'No reviews found. Share your insights with others.',
            );
            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserText).toHaveText('Leave a review!');
        });

        await test.step('Login for review writing and validate the review form.', async () => {
            await ShopCustomer.presses(FrontendProductDetail.reviewTeaserButton);
            await ShopCustomer.expects(FrontendProductDetail.reviewLoginForm).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.forgottenPasswordLink).toBeVisible();
            await ShopCustomer.attemptsTo(LoginViaReviewsTab(blog, user));
            if (InstanceMeta.isSaaS || InstanceMeta.isPaaS) {
                await TestDataService.clearCaches();
            }

            // collapse depend on page-level initialization (JS event listeners, aria-expanded, etc.) which don’t re-fire after DOM patching.
            await ShopCustomer.presses(FrontendProductDetail.reviewsTab);
            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserButton).toBeVisible();
            await ShopCustomer.presses(FrontendProductDetail.reviewTeaserButton);
            await ShopCustomer.expects(FrontendProductDetail.reviewForm).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.reviewRatingPoints).toHaveCount(5);

            const starRatingPoints = await FrontendProductDetail.reviewRatingPoints.count();
            for (let i = 0; i < starRatingPoints; i++) {
                const expectedTexts = [
                    'Unsatisfactory',
                    'Acceptable',
                    'Good',
                    'Very good',
                    'Excellent',
                ];
                await ShopCustomer.selectsRadioButton(FrontendProductDetail.reviewRatingPoints, expectedTexts[i], true);
                await ShopCustomer.expects(async () => {
                    await ShopCustomer.expects(
                        FrontendProductDetail.reviewRatingText.nth(starRatingPoints - (i + 1)),
                    ).toHaveText(expectedTexts[i]);
                }).toPass();
            }
        });

        await test.step('Create a review and validate the submitted review.', async () => {
            await ShopCustomer.selectsRadioButton(FrontendProductDetail.reviewRatingPoints, 'Very good');
            const reviewContent = {
                title: `${blog.name} is a great choice`,
                content: `${blog.name} has a perfect shape and it is easy to use. I can recommend!`,
            };
            await ShopCustomer.fillsIn(FrontendProductDetail.reviewTitleInput, reviewContent.title);
            await ShopCustomer.fillsIn(FrontendProductDetail.reviewReviewTextInput, reviewContent.content);
            await ShopCustomer.presses(FrontendProductDetail.reviewSubmitButton);

            const submitButtonLoadingIcon = FrontendProductDetail.reviewSubmitButton.locator('.loader');
            await ShopCustomer.expects(submitButtonLoadingIcon).toBeVisible();
            await submitButtonLoadingIcon.waitFor({ state: 'hidden' });

            await ShopCustomer.expects(FrontendProductDetail.reviewSubmitMessage).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.reviewCounter).toContainText('1 review');
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(1);
            await ShopCustomer.expects(FrontendProductDetail.reviewItemRatingPoints).toHaveCount(4);
            await ShopCustomer.expects(FrontendProductDetail.reviewItemTitle).toHaveText(reviewContent.title);
            await ShopCustomer.expects(FrontendProductDetail.reviewItemContent).toHaveText(reviewContent.content);

            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserButton).toContainText('Edit review');
            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserText).toContainText(
                'You have already reviewed this blog!',
            );
        });

        await test.step('Logout the user and validate the submitted review is unpublished.', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.presses(FrontendProductDetail.reviewsTab);

            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserButton).toBeVisible();
            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserButton).toContainText('Write review');
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(0);
            await ShopCustomer.expects(FrontendProductDetail.reviewEmptyListingText).toContainText(
                'No reviews found. Share your insights with others.',
            );
            await ShopCustomer.expects(FrontendProductDetail.reviewTeaserText).toContainText('Leave a review!');
        });
    },
);

test(
    'As a shop user, I want to filter reviews, so that I can find the content of a specific rating',
    {
        tag: [
            '@Blog',
            '@Reviews',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, SelectProductReviewOption }) => {
        const blogWithRating1 = await TestDataService.createBasicProduct();
        await TestDataService.createProductReview(blogWithRating1.id, {
            points: 1,
        });
        await TestDataService.createProductReview(blogWithRating1.id, {
            points: 2,
        });
        await TestDataService.createProductReview(blogWithRating1.id, {
            points: 2,
        });

        await test.step('Validate the setup of the filters.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blogWithRating1));
            await ShopCustomer.presses(FrontendProductDetail.reviewsTab);
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(3);

            const reviewFilterExcellent = await FrontendProductDetail.getReviewFilterRowOptionsByName('Excellent');
            await ShopCustomer.expects(reviewFilterExcellent.reviewFilterOptionCheckbox).toBeDisabled();
            await ShopCustomer.expects(reviewFilterExcellent.reviewFilterOptionText).toHaveText('Excellent (0)');
            await ShopCustomer.expects(reviewFilterExcellent.reviewFilterOptionPercentage).toHaveText('0%');

            const reviewFilterVeryGood = await FrontendProductDetail.getReviewFilterRowOptionsByName('Very good');
            await ShopCustomer.expects(reviewFilterVeryGood.reviewFilterOptionCheckbox).toBeDisabled();
            await ShopCustomer.expects(reviewFilterVeryGood.reviewFilterOptionText).toHaveText('Very good (0)');
            await ShopCustomer.expects(reviewFilterVeryGood.reviewFilterOptionPercentage).toHaveText('0%');

            const reviewFilterAcceptable = await FrontendProductDetail.getReviewFilterRowOptionsByName('Acceptable');
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).toBeEnabled();
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionText).toHaveText('Acceptable (2)');
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionPercentage).toHaveText('67%');

            const reviewFilterUnsatisfactory =
                await FrontendProductDetail.getReviewFilterRowOptionsByName('Unsatisfactory');
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).toBeEnabled();
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionText).toHaveText('Unsatisfactory (1)');
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionPercentage).toHaveText('33%');
        });

        await test.step('Validate the functionality of the filters.', async () => {
            const reviewFilterAcceptable = await FrontendProductDetail.getReviewFilterRowOptionsByName('Acceptable');
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).not.toBeChecked();
            await ShopCustomer.attemptsTo(SelectProductReviewOption('Acceptable'));
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).toBeChecked();
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(2);

            await ShopCustomer.attemptsTo(SelectProductReviewOption('Acceptable'));
            await ShopCustomer.expects(reviewFilterAcceptable.reviewFilterOptionCheckbox).not.toBeChecked();

            const reviewFilterUnsatisfactory =
                await FrontendProductDetail.getReviewFilterRowOptionsByName('Unsatisfactory');
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).not.toBeChecked();
            await ShopCustomer.attemptsTo(SelectProductReviewOption('Unsatisfactory'));
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).toBeChecked();
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(1);

            await ShopCustomer.attemptsTo(SelectProductReviewOption('Unsatisfactory'));
            await ShopCustomer.expects(reviewFilterUnsatisfactory.reviewFilterOptionCheckbox).not.toBeChecked();

            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(3);
        });
    },
);

test(
    'As a shop user, I want to filter reviews by rating, log in and come back to the blog detail page.',
    {
        tag: [
            '@Blog',
            '@Reviews',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, TestDataService, FrontendProductDetail, LoginViaReviewsTab }) => {
        const user = await TestDataService.createCustomer();
        const blog = await TestDataService.createBasicProduct();

        await TestDataService.createProductReview(blog.id, { points: 5 });
        await TestDataService.createProductReview(blog.id, { points: 5 });
        await TestDataService.createProductReview(blog.id, { points: 3 });
        await TestDataService.createProductReview(blog.id, { points: 2 });
        await TestDataService.createProductReview(blog.id, { points: 1 });

        await test.step('Navigate to review tab within blog detail page.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.presses(FrontendProductDetail.reviewsTab);
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(5);
        });

        await test.step('Filter down the reviews of the blog by rating', async () => {
            const reviewFilterRowOptions = await FrontendProductDetail.getReviewFilterRowOptionsByName('Excellent (2)');
            await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeEnabled();
            await ShopCustomer.presses(reviewFilterRowOptions.reviewFilterOptionCheckbox);
            await ShopCustomer.expects(reviewFilterRowOptions.reviewFilterOptionCheckbox).toBeChecked();
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(2);
        });

        await test.step('Log in and comes back to the blog detail page', async () => {
            await ShopCustomer.attemptsTo(LoginViaReviewsTab(blog, user));
            await ShopCustomer.presses(FrontendProductDetail.reviewsTab);
            await ShopCustomer.expects(FrontendProductDetail.reviewListingItems).toHaveCount(5);

            await ShopCustomer.expects(FrontendProductDetail.page.locator('h1')).toContainText(blog.name);
        });
    },
);
