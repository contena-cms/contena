import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const DownloadDigitalProductFromOrderAndExpectContentToBe = base.extend<
    { DownloadDigitalProductFromOrderAndExpectContentToBe: Task },
    FixtureTypes
>({
    DownloadDigitalProductFromOrderAndExpectContentToBe: async ({ ShopCustomer, FrontendAccountOrder }, use) => {
        const task = (contentOfFile: string) => {
            return async function DownloadDigitalProductFromOrder() {
                // On 6.8 the download grant runs after the paid request, so refresh the order
                // details until the Download link appears.
                await ShopCustomer.expects(async () => {
                    await FrontendAccountOrder.page.reload();
                    await ShopCustomer.expects(FrontendAccountOrder.orderExpandButton).toBeVisible();
                    await ShopCustomer.presses(FrontendAccountOrder.orderExpandButton);
                    await ShopCustomer.expects(FrontendAccountOrder.digitalProductDownloadButton).toBeVisible();
                }).toPass({ intervals: [500], timeout: 30_000 });

                const [newTab] = await Promise.all([
                    FrontendAccountOrder.page.waitForEvent('popup'),
                    ShopCustomer.presses(FrontendAccountOrder.digitalProductDownloadButton),
                ]);
                ShopCustomer.expects(await newTab.content()).toContain(contentOfFile);
            };
        };

        await use(task);
    },
});
