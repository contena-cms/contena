import { test, expect } from '@fixtures/AcceptanceTest';
import { replaceElements } from '@contena-ag/acceptance-test-suite';

test(
    'Creates a screenshot of the Frontend Homepage.',
    { tag: '@Visual' },
    async ({ ShopCustomer, FrontendHome, Login }) => {
        await test.step('Creates a screenshot and compare it on homepage in frontend.', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(FrontendHome.url());
            await replaceElements(FrontendHome.page, [FrontendHome.categoryTitle]);
            await expect(FrontendHome.page).toHaveScreenshot('Homepage.png', {
                fullPage: true,
            });
        });
    },
);
