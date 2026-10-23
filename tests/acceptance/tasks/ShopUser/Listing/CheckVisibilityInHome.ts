import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

export const CheckVisibilityInHome = base.extend<{ CheckVisibilityInHome: Task }, FixtureTypes>({
    CheckVisibilityInHome: async ({ ShopCustomer, FrontendHome }, use) => {
        const task = (blogName: string) => {
            return async () => {
                const blogLocators = await FrontendHome.getListingItemByProductName(blogName);

                await ShopCustomer.goesTo(FrontendHome.url());
                await ShopCustomer.expects(blogLocators.blogName).toBeVisible();
            };
        };

        await use(task);
    },
});
