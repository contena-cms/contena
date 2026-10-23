import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const AddPromotionCodeToCart = base.extend<{ AddPromotionCodeToCart: Task }, FixtureTypes>({
    AddPromotionCodeToCart: async ({ ShopCustomer, FrontendCheckoutCart }, use) => {
        const task = (promotionName, promotionCode) => {
            return async function AddPromotionCodeToCart() {
                await ShopCustomer.expects(FrontendCheckoutCart.headline).toBeVisible();

                await FrontendCheckoutCart.enterPromoInput.fill(promotionCode);
                await FrontendCheckoutCart.enterPromoInput.press('Enter');

                await ShopCustomer.expects(FrontendCheckoutCart.page.getByText(promotionName)).toBeVisible();
            };
        };

        await use(task);
    },
});
