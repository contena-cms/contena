import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'The Frontend should implement accessibility best practices.',
    { tag: '@Accessibility' },
    async ({
        ShopCustomer,
        TestDataService,
        ValidateAccessibility,
        Login,
        AddProductToCart,
        ProceedFromCartToCheckout,
        ConfirmTermsAndConditions,
        SubmitOrder,
        FrontendCheckoutFinish,
        FrontendProductDetail,
        FrontendCategory,
        FrontendAccountLogin,
        FrontendAccount,
        FrontendAccountOrder,
        FrontendCheckoutCart,
        FrontendAccountProfile,
        FrontendAccountAddresses,
        FrontendPageNotFound,
        InstanceMeta,
    }) => {
        test.slow();

        const blog = await TestDataService.createBasicProduct();
        const category = await TestDataService.createCategory();
        await TestDataService.assignProductCategory(blog.id, category.id);

        await test.step('Login Page Accessibility', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Login', true));
        });

        await test.step('Account Page Accessibility', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendAccount.url());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Account', true));
        });

        await test.step('Category Page Accessibility', async () => {
            await ShopCustomer.goesTo(FrontendCategory.url(category.name));

            // Category has one valid violation, because first level headline can be configured via CMS.
            await ShopCustomer.attemptsTo(ValidateAccessibility('Category', 1));
        });

        await test.step('Blog Detail Page Accessibility', async () => {
            await ShopCustomer.goesTo(FrontendProductDetail.url(blog));
            await ShopCustomer.attemptsTo(ValidateAccessibility('Blog', true));
        });

        await test.step('Cart Page Accessibility', async () => {
            await ShopCustomer.attemptsTo(AddProductToCart(blog, '5'));
            await ShopCustomer.goesTo(FrontendCheckoutCart.url());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Cart', true));
        });

        await test.step('Checkout Accessibility', async () => {
            await ShopCustomer.attemptsTo(ProceedFromCartToCheckout());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Checkout Confirm', true));

            await ShopCustomer.attemptsTo(ConfirmTermsAndConditions());
            await ShopCustomer.attemptsTo(SubmitOrder());
            const orderId = FrontendCheckoutFinish.getOrderId();
            TestDataService.addCreatedRecord('order', orderId);

            await ShopCustomer.attemptsTo(ValidateAccessibility('Checkout Finish', true));
        });

        await test.step('Account Order Accessibility', async () => {
            await ShopCustomer.goesTo(FrontendAccountOrder.url());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Account Order', true));
        });

        await test.step('Account Profile Accessibility', async () => {
            await ShopCustomer.goesTo(FrontendAccountProfile.url());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Account Profile', true));
        });

        await test.step('Account Addresses Accessibility', async () => {
            await ShopCustomer.goesTo(FrontendAccountAddresses.url());
            await ShopCustomer.attemptsTo(ValidateAccessibility('Account Addresses', true));
        });

        // The 404 page title and landmark fixes are not available before version 6.7.14.0.
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (!satisfies(InstanceMeta.version, '<6.7.14.0')) {
            await test.step('Page Not Found Accessibility', async () => {
                await ShopCustomer.goesTo(FrontendPageNotFound.url());
                await ShopCustomer.attemptsTo(ValidateAccessibility('Page Not Found', true));
            });
        }
    },
);
