import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const ConfirmImmediateAccessToDigitalProduct = base.extend<
    { ConfirmImmediateAccessToDigitalProduct: Task },
    FixtureTypes
>({
    ConfirmImmediateAccessToDigitalProduct: async ({ ShopCustomer, FrontendCheckoutConfirm }, use) => {
        const task = () => {
            return async function ConfirmImmediateAccessToDigitalProduct() {
                await FrontendCheckoutConfirm.immediateAccessToDigitalProductCheckbox.check();
                await ShopCustomer.expects(FrontendCheckoutConfirm.immediateAccessToDigitalProductCheckbox).toBeChecked();
            };
        };

        await use(task);
    },
});
