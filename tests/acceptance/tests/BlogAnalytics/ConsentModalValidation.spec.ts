import {
    test,
    expect,
    Page,
    AdminPageObjects,
    createNewAdminPageContext,
    loginToAdministration,
    AdminUser,
} from '@fixtures/AcceptanceTest';
import {
    markUiShellUpdateModalSeen,
    parseCapturedRequests,
    removeSymfonyToolbar,
    setupConsentInterceptor,
    setupProductAnalyticsInterceptor,
    waitForEventCount,
} from '@helpers/productanalytics-helpers';
import { satisfies } from 'compare-versions';

const TRACKING_EVENT_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';
const MONTHS_AHEAD = 3;

test.describe('Blog Analytics - Consent Modal Validation', { tag: '@BlogAnalytics' }, () => {
    test(
        'As a merchant, opening the Blog Analytics consent modal, should send anonymous events.',
        { tag: '@BlogAnalytics' },
        async ({ ChannelBaseConfig, browser, TestDataService, InstanceMeta }) => {
            test.skip(
                satisfies(InstanceMeta.version, '<6.7.9.0'),
                'Data sharing consent modal only available since version 6.7.9.0',
            );

            const { capturedTrackingEventRequests, trackingEventHandler } = setupProductAnalyticsInterceptor();

            const page: Page = await createNewAdminPageContext(browser, ChannelBaseConfig);
            const admin_user: AdminUser = await TestDataService.createUser();
            const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
            const AdminSettingsListing = new AdminPageObjects['SettingsListing'](page);

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });
            await markUiShellUpdateModalSeen(page);

            await test.step('Modify blog analytics API and consent API requests.', async () => {
                const { consentHandler } = setupConsentInterceptor();

                await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentHandler);
            });

            await test.step('Login to contena administration', async () => {
                await loginToAdministration(page, admin_user, TestDataService.AdminApiClient);
            });

            await test.step('Validate modal appeared.', async () => {
                await expect(AdminConsentModal.consentModal).toBeVisible();
                await expect(AdminConsentModal.shareStoreDataCheckbox).not.toBeChecked();
                await expect(AdminConsentModal.shareStoreDataHeadline).toBeVisible();
                await expect(AdminConsentModal.shareStoreDataText).toBeVisible();
                await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible();
                await expect(AdminConsentModal.shareUsageDataText).toBeVisible();
                await expect(AdminConsentModal.shareUsageDataCheckbox).toBeVisible();
                await expect(AdminConsentModal.shareUsageDataCheckbox).not.toBeChecked();
                await expect(AdminConsentModal.storeDataCollectionDetailsLink).toBeVisible();
                await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();
                await expect(AdminConsentModal.allowAllButton).toBeVisible();
                await expect(AdminConsentModal.rejectAllButton).toBeVisible();
            });

            await test.step('Reject all consents.', async () => {
                await removeSymfonyToolbar(page);
                await AdminConsentModal.rejectAllButton.click();
            });

            await test.step('Validate modal disappeared.', async () => {
                await expect(AdminConsentModal.consentModal).toBeHidden();
            });

            await test.step('Validate anonymous events are fired.', async () => {
                // Cumulative expected events: 1 consent_modal_viewed + 2 consent_status_change + 1 consent_modal_decision = 4.
                const requests = parseCapturedRequests(capturedTrackingEventRequests);
                expect(requests.length).toBeGreaterThanOrEqual(1);

                const getAnalyticsEvents = () =>
                    parseCapturedRequests(capturedTrackingEventRequests).flatMap((request) => request.events);

                await waitForEventCount(getAnalyticsEvents, 4);

                const events = getAnalyticsEvents();

                const consentModalViewedEvents = events.filter((e) => e.name === 'consent_modal_viewed');
                const consentStatusChangeEvents = events.filter((e) => e.name === 'consent_status_change');
                const consentModalDecisionEvents = events.filter((e) => e.name === 'consent_modal_decision');

                expect(consentModalViewedEvents).toHaveLength(1);
                expect(consentStatusChangeEvents).toHaveLength(2);
                expect(consentModalDecisionEvents).toHaveLength(1);

                expect(consentModalViewedEvents).toEqual(
                    expect.arrayContaining([
                        expect.objectContaining({
                            properties: expect.objectContaining({
                                consents_shown: expect.arrayContaining([
                                    'backend_data',
                                    'blog_analytics',
                                ]),
                            }),
                        }),
                    ]),
                );

                expect(consentStatusChangeEvents).toEqual(
                    expect.arrayContaining([
                        expect.objectContaining({
                            properties: expect.objectContaining({
                                consent: 'backend_data',
                                status: 'declined',
                            }),
                        }),
                        expect.objectContaining({
                            properties: expect.objectContaining({
                                consent: 'blog_analytics',
                                status: 'declined',
                            }),
                        }),
                    ]),
                );

                expect(consentModalDecisionEvents).toEqual(
                    expect.arrayContaining([
                        expect.objectContaining({
                            properties: expect.objectContaining({
                                backend_data_changed: false,
                                backend_data_state: 'revoked',
                                blog_analytics_changed: false,
                                blog_analytics_state: 'revoked',
                            }),
                        }),
                    ]),
                );
            });

            await test.step('Validate no further captured requests for blog analytics after revoke.', async () => {
                // make sure consent modal is not shown
                const { consentHandler } = setupConsentInterceptor({
                    backend_data: 'declined',
                    blog_analytics: 'declined',
                });
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);

                await page.goto(AdminSettingsListing.url());
                await AdminSettingsListing.privacyLink.click();

                const getAnalyticsEvents = () =>
                    parseCapturedRequests(capturedTrackingEventRequests).flatMap((request) => request.events);

                // Cumulative expected events stay at 4 (0 additional events after opening privacy settings).
                await waitForEventCount(getAnalyticsEvents, 4);
            });

            await test.step('Validate backend data consent is false in UI by default.', async () => {
                const AdminDataSharing = new AdminPageObjects['DataSharing'](page, InstanceMeta);
                await expect(AdminDataSharing.dataSharingStoreDataCheckbox).not.toBeChecked();
                await expect(AdminDataSharing.dataSharingStoreDataCheckbox).toBeEditable();
            });

            await test.step('Cleanup created admin_user.', async () => {
                await page.close();
            });
        },
    );

    test(
        'Existing backend-data consent is checked before rendering consent modal',
        { tag: '@BlogAnalytics' },
        async ({ ChannelBaseConfig, browser, TestDataService, InstanceMeta }) => {
            test.skip(
                satisfies(InstanceMeta.version, '<6.7.9.0'),
                'Data sharing consent modal only available since version 6.7.9.0',
            );

            const page: Page = await createNewAdminPageContext(browser, ChannelBaseConfig);
            const admin_user: AdminUser = await TestDataService.createUser();

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });
            await markUiShellUpdateModalSeen(page);

            await test.step('Modify blog analytics API and consent API requests.', async () => {
                const { trackingEventHandler } = setupProductAnalyticsInterceptor();
                const { consentHandler } = setupConsentInterceptor({ backend_data: 'accepted' });

                await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
            });

            await test.step('Login to contena administration', async () => {
                await loginToAdministration(page, admin_user, TestDataService.AdminApiClient);
            });

            await test.step('Validate no store data consent option available.', async () => {
                const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

                await expect(AdminConsentModal.consentModal).toBeVisible();
                await expect(AdminConsentModal.shareStoreDataCheckbox).toHaveCount(0);
                await expect(AdminConsentModal.shareUsageDataCheckbox).toHaveCount(0);
                await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible();
                await expect(AdminConsentModal.shareUsageDataText).toBeVisible();
                await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();
            });

            await test.step('Cleanup.', async () => {
                await page.close();
            });
        },
    );

    test(
        'Only authorized users in administration can change store consent and admin_user data consent',
        { tag: '@BlogAnalytics' },
        async ({ TestDataService, ChannelBaseConfig, browser, InstanceMeta }) => {
            test.skip(
                satisfies(InstanceMeta.version, '<6.7.9.0'),
                'Data sharing consent modal only available since version 6.7.9.0',
            );

            const page: Page = await createNewAdminPageContext(browser, ChannelBaseConfig);
            const admin_user: AdminUser = await TestDataService.createUser({ admin: false });

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });
            await markUiShellUpdateModalSeen(page);

            await test.step('Modify blog analytics API and consent API requests.', async () => {
                const { trackingEventHandler } = setupProductAnalyticsInterceptor();
                const { consentHandler } = setupConsentInterceptor();

                await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentHandler);
            });

            await test.step('Setup admin_user which can not change store consent but admin_user data consent', async () => {
                const permissions = [
                    'system_config:read',
                    'admin_user.update_profile',
                    'admin_user:read',
                    'admin_user_change_me',
                    'admin_user_config:create',
                    'admin_user_config:read',
                    'admin_user_config:update',
                ];

                const onlyChangeUserProfilePermissions = await TestDataService.createAclRole({
                    privileges: permissions,
                });
                await TestDataService.assignAclRoleUser(onlyChangeUserProfilePermissions.id, admin_user.id);
            });

            await test.step('Login to contena administration', async () => {
                await loginToAdministration(page, admin_user, TestDataService.AdminApiClient);
            });

            await test.step('Validate no store data consent option available.', async () => {
                const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
                const AdminYourProfile = new AdminPageObjects['YourProfile'](page);

                await AdminYourProfile.page.goto(AdminYourProfile.url('privacy-preferences'));

                await expect(AdminConsentModal.consentModal).toBeVisible({ timeout: 10_000 });
                await expect(AdminConsentModal.shareStoreDataCheckbox).toHaveCount(0);
                await expect(AdminConsentModal.shareUsageDataCheckbox).toHaveCount(0);
                await expect(AdminConsentModal.shareUsageDataHeadline).toBeVisible();
                await expect(AdminConsentModal.shareUsageDataText).toBeVisible();
                await expect(AdminConsentModal.privacyPolicyLink).toBeVisible();

                await AdminConsentModal.page.getByRole('button', { name: 'Decline' }).click();

                await expect(AdminYourProfile.dataSharingUsageDataCheckbox).toBeEditable();
                await expect(AdminYourProfile.dataSharingUsageDataCheckbox).not.toBeChecked();
            });

            await test.step('Cleanup.', async () => {
                await page.close();
            });
        },
    );

    test(
        'Each admin_user can only manage their own admin_user-data consent.',
        { tag: '@BlogAnalytics' },
        async ({ ChannelBaseConfig, browser, TestDataService, InstanceMeta }) => {
            test.skip(
                satisfies(InstanceMeta.version, '<6.7.9.0'),
                'Data sharing consent modal only available since version 6.7.9.0',
            );

            const page: Page = await createNewAdminPageContext(browser, ChannelBaseConfig);
            const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

            await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });
            await markUiShellUpdateModalSeen(page);

            await test.step('Modify blog analytics API and consent API requests.', async () => {
                const { trackingEventHandler } = setupProductAnalyticsInterceptor();
                const { consentHandler } = setupConsentInterceptor();

                await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/accept`, consentHandler);
                await page.route(`**/${CONSENTS_ENDPOINT}/revoke`, consentHandler);
            });

            await test.step('Login to contena administration with first admin_user', async () => {
                const admin_user1: AdminUser = await TestDataService.createUser();

                await loginToAdministration(page, admin_user1, TestDataService.AdminApiClient);

                await removeSymfonyToolbar(page);
                await AdminConsentModal.shareUsageDataCheckbox.click();
                await AdminConsentModal.savePreferencesButton.click();
            });

            await test.step('Validate second admin_user sees their own consent.', async () => {
                const page = await createNewAdminPageContext(browser, ChannelBaseConfig);
                const AdminConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
                const admin_user2: AdminUser = await TestDataService.createUser();

                await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });
                await markUiShellUpdateModalSeen(page);

                await loginToAdministration(page, admin_user2, TestDataService.AdminApiClient);

                await removeSymfonyToolbar(page);
                await AdminConsentModal.shareUsageDataCheckbox.scrollIntoViewIfNeeded();
                await expect(AdminConsentModal.shareUsageDataCheckbox).not.toBeChecked();
                await expect(AdminConsentModal.shareUsageDataCheckbox).toBeEditable();

                await page.close();
            });

            await test.step('Cleanup.', async () => {
                await page.close();
            });
        },
    );
});
