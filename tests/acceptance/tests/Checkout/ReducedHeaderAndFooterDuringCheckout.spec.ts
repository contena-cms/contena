import { test } from '@fixtures/AcceptanceTest';

test(
    'As a user, I want to see a reduced header and footer during checkout.',
    {
        tag: [
            '@Checkout',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        TestDataService,
        FrontendProductDetail,
        Login,
        AddProductToCart,
        ProceedFromProductToCheckout,
        FrontendSearchSuggest,
        FrontendHeader,
        FrontendFooter,
    }) => {
        const basicProduct = await TestDataService.createBasicProduct();
        await ShopCustomer.attemptsTo(Login());

        await test.step('Validate that the full header and footer are visible on the blog detail page.', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(basicProduct));
            await ShopCustomer.expects(FrontendSearchSuggest.searchInput).toBeVisible();
            const numOfLocators = await FrontendHeader.mainNavigationLink.count();
            ShopCustomer.expects(numOfLocators).toBeGreaterThan(0);
            await ShopCustomer.expects(FrontendFooter.footerHeadline).toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerContent).toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerHotline).toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerContactForm).toBeVisible();
        });

        await test.step('Validate that the full header and footer are not visible on the checkout page.', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(basicProduct));
            await ShopCustomer.attemptsTo(ProceedFromProductToCheckout());
            await ShopCustomer.expects(FrontendSearchSuggest.searchInput).not.toBeVisible();
            await ShopCustomer.expects(FrontendHeader.mainNavigationLink).not.toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerHeadline).not.toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerContent).not.toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerHotline).not.toBeVisible();
            await ShopCustomer.expects(FrontendFooter.footerContactForm).not.toBeVisible();
        });
    },
);
