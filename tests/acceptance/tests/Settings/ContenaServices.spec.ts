import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';
import { expect } from '@playwright/test';

test.describe('Contena Services', () => {
    test.describe.configure({ mode: 'serial' });

    let initialServicesState: boolean;

    test.beforeAll(async ({ ShopAdmin, AdminContenaServices, InstanceMeta }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

        await ShopAdmin.goesTo(AdminContenaServices.url());
        initialServicesState = await AdminContenaServices.deactivateServicesButton
            .isVisible({ timeout: 5000 })
            .catch(() => false);
    });

    test.afterAll(async ({ ShopAdmin, AdminContenaServices, InstanceMeta }) => {
        if (satisfies(InstanceMeta.version, '>=6.7.1')) {
            try {
                await ShopAdmin.goesTo(AdminContenaServices.url());
                const isCurrentlyActive = await AdminContenaServices.deactivateServicesButton
                    .isVisible()
                    .catch(() => false);

                if (initialServicesState && !isCurrentlyActive) {
                    const enableResponsePromise = AdminContenaServices.page.waitForResponse(
                        (response) =>
                            response.url().includes('/api/services/enable') && response.request().method() === 'POST',
                        { timeout: 20000 },
                    );
                    await AdminContenaServices.activateServicesButton.click();
                    await enableResponsePromise;
                    await AdminContenaServices.page.reload();
                    await ShopAdmin.expects(AdminContenaServices.deactivateServicesButton).toBeVisible({
                        timeout: 15000,
                    });
                }
            } catch (error) {
                console.error('Failed to restore Contena Services state:', error);
            }
        }
    });

    // eslint-disable-next-line playwright/no-skipped-test
    test.skip(
        'As a merchant, I want to fully deactivate the Contena Services feature.',
        {
            tag: '@Settings',
            annotation: {
                type: 'issue',
                description: 'https://github.com/contena-cms/contena/issues/17082',
            },
        },
        async ({ ShopAdmin, AdminContenaServices, InstanceMeta }) => {
            test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

            await ShopAdmin.goesTo(AdminContenaServices.url());

            await ShopAdmin.expects(AdminContenaServices.header).toBeVisible({ timeout: 10000 });
            await ShopAdmin.expects(AdminContenaServices.header).toHaveText(
                'Future proof your store with Contena Services',
            );

            await ShopAdmin.expects(AdminContenaServices.deactivateServicesButton).toBeVisible();
            await ShopAdmin.expects(AdminContenaServices.deactivateServicesButton).toBeEnabled();

            const disableResponsePromise = AdminContenaServices.page.waitForResponse(
                (response) => response.url().includes('/api/services/disable') && response.request().method() === 'POST',
                { timeout: 20000 },
            );

            await AdminContenaServices.deactivateServicesButton.click();
            await ShopAdmin.expects(AdminContenaServices.deactivateServicesModal).toBeVisible();
            await AdminContenaServices.deactivateServicesConfirmButton.click();

            const disableResponse = await disableResponsePromise;
            expect(disableResponse.ok()).toBeTruthy();

            await ShopAdmin.expects(AdminContenaServices.deactivatedBanner).toBeVisible({ timeout: 15000 });
            await ShopAdmin.expects(AdminContenaServices.activateServicesButton).toBeVisible();
            await ShopAdmin.expects(AdminContenaServices.permissionBanner).not.toBeVisible();
            await ShopAdmin.expects(AdminContenaServices.serviceCards).not.toBeVisible();

            const enableResponsePromise = AdminContenaServices.page.waitForResponse(
                (response) => response.url().includes('/api/services/enable') && response.request().method() === 'POST',
                { timeout: 20000 },
            );

            await AdminContenaServices.activateServicesButton.click();
            const enableResponse = await enableResponsePromise;
            expect(enableResponse.ok()).toBeTruthy();

            await AdminContenaServices.page.reload();
            await ShopAdmin.expects(AdminContenaServices.deactivateServicesButton).toBeVisible({ timeout: 15000 });
            await ShopAdmin.expects(AdminContenaServices.header).toBeVisible();
        },
    );

    test(
        'As a merchant, I can manage Contena Services only if the necessary permissions are granted.',
        { tag: '@Settings' },
        async ({ ShopAdmin, TestDataService, CheckAccessToContenaServices, InstanceMeta }) => {
            test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');
            // The `CheckAccessToContenaServices` task enters the services page through the dashboard
            // advertisement banner, which was removed from the dashboard. Re-enable once the task in
            // @contena-ag/acceptance-test-suite navigates to `sw.settings.services.index` directly.
            test.skip(true, 'Task depends on the removed services dashboard banner.');

            await test.step('Verify insufficient permissions prevent access to services.', async () => {
                let aclRole;
                // Create a role with insufficient permissions
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (InstanceMeta.isSaaS) {
                    const privileges = [
                        'cms_page:read',
                        'custom_field:read',
                        'custom_field_set_relation:read',
                        'blog_sorting:create',
                        'blog_sorting:delete',
                        'blog_sorting:read',
                        'blog_sorting:update',
                        'channel:read',
                        'seo_url_template:create',
                        'seo_url_template:read',
                        'seo_url_template:update',
                        'system.system_config',
                        'system_config:create',
                        'system_config:delete',
                        'system_config:read',
                        'system_config:update',
                        'channel_domain:read',
                        'swag_language_pack_language:read',
                    ];
                    aclRole = await TestDataService.createAclRole({ privileges: privileges });
                } else {
                    aclRole = await TestDataService.createAclRole();
                }
                const admin_user = await TestDataService.createUser();
                await TestDataService.assignAclRoleUser(aclRole.id, admin_user.id);

                await ShopAdmin.attemptsTo(CheckAccessToContenaServices(admin_user, aclRole));
            });

            await test.step('Verify minimum permissions are enough to manage Contena Services.', async () => {
                let aclRole;
                // Basic permissions to access the services
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (InstanceMeta.isSaaS) {
                    const privileges = [
                        'cms_page:read',
                        'custom_field:read',
                        'custom_field_set_relation:read',
                        'plugin:update',
                        'blog_sorting:create',
                        'blog_sorting:delete',
                        'blog_sorting:read',
                        'blog_sorting:update',
                        'channel:read',
                        'seo_url_template:create',
                        'seo_url_template:read',
                        'seo_url_template:update',
                        'system.plugin_maintain',
                        'system.system_config',
                        'system:clear:cache',
                        'system:plugin:maintain',
                        'system_config:create',
                        'system_config:delete',
                        'system_config:read',
                        'system_config:update',
                        'channel_domain:read',
                        'swag_language_pack_language:read',
                    ];
                    aclRole = await TestDataService.createAclRole({ privileges: privileges });
                } else {
                    const privileges = [
                        'cms_page:read',
                        'custom_field:read',
                        'custom_field_set_relation:read',
                        'plugin:update',
                        'blog_sorting:create',
                        'blog_sorting:delete',
                        'blog_sorting:read',
                        'blog_sorting:update',
                        'channel:read',
                        'seo_url_template:create',
                        'seo_url_template:read',
                        'seo_url_template:update',
                        'system.plugin_maintain',
                        'system.system_config',
                        'system:clear:cache',
                        'system:plugin:maintain',
                        'system_config:create',
                        'system_config:delete',
                        'system_config:read',
                        'system_config:update',
                    ];
                    aclRole = await TestDataService.createAclRole({ privileges: privileges });
                }
                const admin_user = await TestDataService.createUser();
                await TestDataService.assignAclRoleUser(aclRole.id, admin_user.id);

                await ShopAdmin.attemptsTo(CheckAccessToContenaServices(admin_user, aclRole));
            });
        },
    );
});
