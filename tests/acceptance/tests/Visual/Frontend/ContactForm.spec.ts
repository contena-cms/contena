import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Creates a screenshot of the Frontend Homepage.',
    { tag: '@Visual' },
    async ({ ShopCustomer, FrontendAccountLogin, FrontendFooter, FrontendContactForm, TestDataService }) => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot and compare it on homepage in frontend.', async () => {
            await ShopCustomer.goesTo(FrontendAccountLogin.url());
            await FrontendFooter.footerContactFormLink.click();
            await ShopCustomer.expects(FrontendContactForm.cardTitle).toContainText('Contact');
            await expect(FrontendContactForm.page).toHaveScreenshot('Contact-Form.png', {
                fullPage: true,
            });
        });
    },
);
