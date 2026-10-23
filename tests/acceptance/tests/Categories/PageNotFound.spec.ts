import { test } from '@fixtures/AcceptanceTest';

test(
    'As a user, I want to see 404 layout when I navigate to a non-existing page.',
    {
        tag: [
            '@Categories',
            '@Frontend',
        ],
    },
    async ({ ShopCustomer, FrontendPageNotFound, FrontendHome }) => {
        await ShopCustomer.goesTo(FrontendPageNotFound.url());

        await ShopCustomer.expects(FrontendPageNotFound.pageNotFoundImage).toBeVisible();
        await ShopCustomer.expects(FrontendPageNotFound.headline).toHaveText('Page not found');
        await ShopCustomer.expects(FrontendPageNotFound.pageNotFoundMessage).toHaveText(
            `We are sorry, the page you're looking for could not be found. It may no longer exist or may have been moved.`,
        );
        await ShopCustomer.presses(FrontendPageNotFound.backToShopButton);
        await ShopCustomer.expects(FrontendHome.page).toHaveURL(FrontendHome.url());
        await ShopCustomer.expects(FrontendHome.categoryTitle).toBeVisible();
    },
);
