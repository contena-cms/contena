import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const UserGroupActivation = base.extend<{ UserGroupActivation: Task }, FixtureTypes>({
    UserGroupActivation: async ({ ShopAdmin, AdminCustomerListing, AdminCustomerDetail }, use) => {
        const task = (email: string, userGroupName: string) => {
            return async function UserGroupActivation() {
                await ShopAdmin.goesTo(AdminCustomerListing.url());
                const userLineItem = await AdminCustomerListing.getCustomerByEmail(email);
                await ShopAdmin.expects(userLineItem.userGroup).toBeVisible({ timeout: 10000 });
                await userLineItem.userName.click();
                const userGroupAlert = await AdminCustomerDetail.getCustomerGroupAlert(userGroupName);
                await ShopAdmin.expects(userGroupAlert).toContainText(userGroupName);
                await ShopAdmin.expects(AdminCustomerDetail.userGroupRequestMessage).toBeVisible();
                const responsePromise = AdminCustomerDetail.page.waitForResponse(
                    '**/api/_action/user-group-registration/accept',
                );
                await AdminCustomerDetail.userGroupAcceptButton.click();
                const userGroupResponse = await responsePromise;
                expect(userGroupResponse).toBeTruthy();
            };
        };

        await use(task);
    },
});
