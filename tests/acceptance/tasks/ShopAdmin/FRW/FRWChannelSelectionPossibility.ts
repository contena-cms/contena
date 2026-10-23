import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * @sw-package fundamentals@after-sales
 */
export const FRWSalesChannelSelectionPossibility = base.extend<{ FRWSalesChannelSelectionPossibility: Task }, FixtureTypes>({
    FRWSalesChannelSelectionPossibility: async ({ AdminFirstRunWizard }, use) => {
        const task = (channelName: string) => {
            return async function FRWSalesChannelSelectionPossibility() {
                await AdminFirstRunWizard.channelSelectionMultiSelect.click();
                await expect(
                    AdminFirstRunWizard.channelSelectionList.filter({ hasText: channelName }).first(),
                ).toBeVisible();
            };
        };

        await use(task);
    },
});
