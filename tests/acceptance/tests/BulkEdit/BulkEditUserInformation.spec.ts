import { test } from '@fixtures/AcceptanceTest';

const TIMEOUT = 15_000;

test(
    'As a merchant, I can perform bulk edits on user information',
    { tag: '@BulkEdits' },
    async ({
        TestDataService,
        ShopAdmin,
        AdminCustomerListing,
        AdminCustomerDetail,
        BulkEditCustomers,
        IdProvider,
        DefaultSalesChannel,
    }) => {
        let i = 1;
        const user1 = await TestDataService.createCustomer({ firstName: `Bulk edit ${i++}` });
        const user2 = await TestDataService.createCustomer({ firstName: `Bulk edit ${i++}` });
        const user3 = await TestDataService.createCustomer({ firstName: `Bulk edit ${i++}` });
        const currentCustomerGroup = await TestDataService.getCustomerGroupById(user3.groupId);
        const userGroupToUpdate = await TestDataService.createCustomerGroup();
        const currentLanguage = await TestDataService.getLanguageById(user3.languageId);
        const languageToUpdate = await TestDataService.getLanguageData('de-DE');
        await TestDataService.assignSalesChannelLanguage(DefaultSalesChannel.channel.id, languageToUpdate.id);
        const tagName1 = `001_bulk_edit_${user1.id}`;
        const tagName2 = `002_bulk_edit_${user2.id}`;
        await TestDataService.createTag(tagName1);
        await TestDataService.createTag(tagName2);
        const tagData = {
            changeType: 'Overwrite',
            tags: [
                tagName1,
                tagName2,
            ],
        };
        const accountData = {
            userGroup: userGroupToUpdate.name,
            accountStatus: false,
            language: languageToUpdate.name,
        };
        const customFieldSetId = IdProvider.getIdPair().uuid;
        const customFieldSetName = `cmf_set_${IdProvider.getIdPair().id}`;
        const customFieldTextName = `cmf_set_text_${IdProvider.getIdPair().id}`;
        const customFieldValue = `cmf_value_${IdProvider.getIdPair().id}`;
        const customFieldData = {
            customFieldSetName: customFieldSetName,
            customFieldName: customFieldTextName,
            customFieldValue: customFieldValue,
        };

        await test.step('Prepares a custom field set', async () => {
            await TestDataService.createCustomFieldSet({ id: customFieldSetId, name: customFieldSetName });
            await TestDataService.createCustomField(customFieldSetId, {
                name: customFieldTextName,
                config: {
                    label: {
                        'en-GB': customFieldTextName,
                    },
                },
            });
        });

        await test.step('Merchant bulk edits two users', async () => {
            await ShopAdmin.goesTo(AdminCustomerListing.url());
            const users = [
                user1,
                user2,
            ];
            await ShopAdmin.attemptsTo(BulkEditCustomers(users, accountData, tagData, customFieldData));
        });

        await test.step('Verifies the changes applied for bulk edited users', async () => {
            for (const user of [
                user1,
                user2,
            ]) {
                //verify general information
                await ShopAdmin.goesTo(AdminCustomerDetail.url(user.id));
                const admin_userCustomerGroup = await AdminCustomerDetail.getCustomerGroup();
                await ShopAdmin.expects(admin_userCustomerGroup).toHaveText(accountData.userGroup, { timeout: TIMEOUT });
                const accountStatus = await AdminCustomerDetail.getAccountStatus();
                await ShopAdmin.expects(accountStatus).toHaveText(accountData.accountStatus ? 'Active' : 'Inactive');
                const language = await AdminCustomerDetail.getLanguage();
                await ShopAdmin.expects(language).toHaveText(accountData.language);
                //verify tags
                const tags = await AdminCustomerDetail.tagList.textContent();
                ShopAdmin.expects(tags).toContain(tagName1);
                ShopAdmin.expects(tags).toContain(tagName2);
                ShopAdmin.expects(await AdminCustomerDetail.tagItems.all()).toHaveLength(2);
                //Verify custom field
                const customFieldSetTabContent =
                    await AdminCustomerDetail.getCustomFieldSetCardContentByName(customFieldSetName);
                await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
                await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
                await customFieldSetTabContent.customFieldSetTab.click();
                await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
                await ShopAdmin.expects(
                    customFieldSetTabContent.customFieldSetTabCustomContent.getByText(customFieldTextName),
                ).toBeVisible();
                await ShopAdmin.expects(
                    customFieldSetTabContent.customFieldSetTabCustomContent.locator(`#${customFieldTextName}`),
                ).toHaveValue(customFieldValue, { timeout: TIMEOUT });
            }
        });

        await test.step('Verify that changes are not applied to other users', async () => {
            await ShopAdmin.goesTo(AdminCustomerDetail.url(user3.id), true);
            const admin_userCustomerGroup = await AdminCustomerDetail.getCustomerGroup();
            await ShopAdmin.expects(admin_userCustomerGroup).toHaveText(currentCustomerGroup.name, { timeout: TIMEOUT });
            const accountStatus = await AdminCustomerDetail.getAccountStatus();
            await ShopAdmin.expects(accountStatus).toHaveText(user3.active ? 'Active' : 'Inactive');
            const language = await AdminCustomerDetail.getLanguage();
            await ShopAdmin.expects(language).toHaveText(currentLanguage.name);
            ShopAdmin.expects(await AdminCustomerDetail.tagItems.all()).toHaveLength(0);
            const customFieldSetTabContent =
                await AdminCustomerDetail.getCustomFieldSetCardContentByName(customFieldSetName);
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toBeVisible();
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTab).toHaveText(customFieldSetName);
            await customFieldSetTabContent.customFieldSetTab.click();
            await ShopAdmin.expects(customFieldSetTabContent.customFieldSetTabCustomContent).toBeVisible();
            await ShopAdmin.expects(
                customFieldSetTabContent.customFieldSetTabCustomContent.getByText(customFieldTextName),
            ).toBeVisible();
            await ShopAdmin.expects(
                customFieldSetTabContent.customFieldSetTabCustomContent.locator(`#${customFieldTextName}`),
            ).toHaveValue('', { timeout: TIMEOUT });
        });
    },
);
