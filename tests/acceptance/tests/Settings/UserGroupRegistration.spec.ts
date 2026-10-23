import { test } from '@fixtures/AcceptanceTest';

test(
    'As an admin, I can create and verify user groups in the admin.',
    { tag: '@UserGroups' },
    async ({ TestDataService, ShopAdmin, AdminCustomerGroupListing, AdminCustomerGroupDetail, DefaultSalesChannel }) => {
        const userGroup = await TestDataService.createCustomerGroup();

        await test.step('Verify the created user group in the admin', async () => {
            await ShopAdmin.goesTo(AdminCustomerGroupListing.url([userGroup.name]));
            const userGroupLineItem = await AdminCustomerGroupListing.getCustomerGroupByName(userGroup.name);
            await ShopAdmin.expects(userGroupLineItem.userGroupName).toBeVisible({ timeout: 10000 });

            await ShopAdmin.goesTo(AdminCustomerGroupDetail.url(userGroup.id));
            await ShopAdmin.expects(AdminCustomerGroupDetail.headline).toContainText(userGroup.name);
            await ShopAdmin.expects(AdminCustomerGroupDetail.userGroupNameField).toHaveValue(userGroup.name);
            await ShopAdmin.expects(AdminCustomerGroupDetail.userGroupGrossTaxDisplay).toBeChecked();
            await ShopAdmin.expects(AdminCustomerGroupDetail.customSignupFormToggle).toBeChecked();
            await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormTitle).toHaveValue(userGroup.name);
            await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormIntroduction).toContainText(
                `${userGroup.name}-Introduction`,
            );
            await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormSeoDescription).toHaveValue(
                `${userGroup.name}-SEO-Description`,
            );
            await ShopAdmin.expects(AdminCustomerGroupDetail.signupFormCompanySignupToggle).not.toBeChecked();
            await ShopAdmin.expects(AdminCustomerGroupDetail.selectedSalesChannel).toContainText(
                DefaultSalesChannel.channel.name,
            );
            await ShopAdmin.expects(AdminCustomerGroupDetail.technicalUrl).toHaveValue(new RegExp(`${userGroup.id}`));
            await ShopAdmin.expects(AdminCustomerGroupDetail.saleschannelUrl).toHaveValue(
                new RegExp(`${userGroup.name}`),
            );
        });
    },
);
test(
    'As a user, I can register under a user group in the Frontend.',
    { tag: '@Registration @UserGroups' },
    async ({
        TestDataService,
        ShopAdmin,
        ShopCustomer,
        FrontendAccount,
        FrontendCustomRegister,
        IdProvider,
        Register,
        UserGroupActivation,
    }) => {
        const user = { email: IdProvider.getIdPair().uuid + '@test.com' };
        const userGroup = await TestDataService.createCustomerGroup();

        await test.step('Register the user and activate it for the user group', async () => {
            await ShopCustomer.goesTo(FrontendCustomRegister.url(userGroup.name));
            await ShopCustomer.attemptsTo(Register(user));
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
            const userGroupAlert = await FrontendAccount.getCustomerGroupAlert(userGroup.name);
            await ShopCustomer.expects(userGroupAlert).toContainText(userGroup.name);
            await ShopAdmin.attemptsTo(UserGroupActivation(user.email, userGroup.name));
        });

        await test.step('Verify that the user group request message is not displayed on the Frontend', async () => {
            await ShopCustomer.goesTo(FrontendAccount.url());
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
            await ShopCustomer.expects(FrontendAccount.userGroupRequestMessage).not.toBeVisible();
        });
    },
);

test(
    'As a commercial user, I can register under a user group in the Frontend.',
    { tag: '@Registration @UserGroups' },
    async ({
        TestDataService,
        ShopAdmin,
        ShopCustomer,
        FrontendAccount,
        FrontendCustomRegister,
        IdProvider,
        Register,
        UserGroupActivation,
    }) => {
        const uuid = IdProvider.getIdPair().uuid;
        const user = { isCommercial: true, email: uuid + '@test.com', vatRegNo: uuid + '-VatId' };
        const commercialCustomerGroup = await TestDataService.createCustomerGroup({
            registrationOnlyCompanyRegistration: true,
        });

        await test.step('Register the commercial user and activate it for the user group', async () => {
            await ShopCustomer.goesTo(FrontendCustomRegister.url(commercialCustomerGroup.name));
            await ShopCustomer.attemptsTo(Register(user));
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
            const userGroupAlert = await FrontendAccount.getCustomerGroupAlert(commercialCustomerGroup.name);
            await ShopCustomer.expects(userGroupAlert).toContainText(commercialCustomerGroup.name);
            await ShopAdmin.attemptsTo(UserGroupActivation(user.email, commercialCustomerGroup.name));
        });

        await test.step('Verify that the user group request message is not displayed on the Frontend', async () => {
            await ShopCustomer.goesTo(FrontendAccount.url());
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.email, { exact: true })).toBeVisible();
            await ShopCustomer.expects(FrontendAccount.page.getByText(user.vatRegNo.toUpperCase())).toBeVisible();
            await ShopCustomer.expects(FrontendAccount.userGroupRequestMessage).not.toBeVisible();
        });
    },
);
