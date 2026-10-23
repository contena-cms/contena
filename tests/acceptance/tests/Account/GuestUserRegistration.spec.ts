import { test } from '@fixtures/AcceptanceTest';

test(
    'Guest user must be able to register in the Frontend.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        FrontendProductDetail,
        FrontendHome,
        FrontendAccountLogin,
        FrontendCheckoutRegister,
        AddProductToCart,
        TestDataService,
        Register,
    }) => {
        const blog = await TestDataService.createBasicProduct();

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.expects(FrontendProductDetail.page).toHaveTitle(
            `${blog.translated.name} | ${blog.blogNumber}`,
        );
        await ShopCustomer.attemptsTo(AddProductToCart(blog));
        await ShopCustomer.presses(FrontendProductDetail.offCanvasCartGoToCheckoutButton);
        await FrontendCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });
        await ShopCustomer.expects(FrontendCheckoutRegister.page.locator('.register-submit button')).toContainText(
            /Continue|Weiter/i,
        );

        await ShopCustomer.attemptsTo(Register({ isGuest: true }));
        await ShopCustomer.goesTo(FrontendHome.url());
        await ShopCustomer.presses(FrontendHome.accountMenuButton);
        await ShopCustomer.presses(FrontendHome.closeGuestSessionButton);
        await ShopCustomer.expects(FrontendAccountLogin.successAlert).toBeVisible();
    },
);

test(
    'Guest commercial user must be able to register in the Frontend.',
    {
        tag: [
            '@Registration',
            '@Frontend',
        ],
    },
    async ({
        ShopCustomer,
        FrontendHome,
        FrontendProductDetail,
        FrontendAccountLogin,
        FrontendCheckoutRegister,
        AddProductToCart,
        TestDataService,
        Register,
    }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
        const blog = await TestDataService.createBasicProduct();

        await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
        await ShopCustomer.expects(FrontendProductDetail.page).toHaveTitle(
            `${blog.translated.name} | ${blog.blogNumber}`,
        );
        await ShopCustomer.attemptsTo(AddProductToCart(blog));
        await ShopCustomer.presses(FrontendProductDetail.offCanvasCartGoToCheckoutButton);
        await FrontendCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });

        await ShopCustomer.presses(FrontendAccountLogin.accountTypeSelect);
        await FrontendAccountLogin.accountTypeSelect.selectOption('Commercial');
        await ShopCustomer.attemptsTo(Register({ isCommercial: true, isGuest: true }));
        await ShopCustomer.goesTo(FrontendHome.url());
        await ShopCustomer.presses(FrontendHome.accountMenuButton);
        await ShopCustomer.presses(FrontendHome.closeGuestSessionButton);
        await ShopCustomer.expects(FrontendAccountLogin.successAlert).toBeVisible();
    },
);
