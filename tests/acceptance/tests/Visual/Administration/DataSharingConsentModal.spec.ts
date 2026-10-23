import { test, assertScreenshot, setViewport, Page, replaceElements, hideElements } from '@fixtures/AcceptanceTest';
import {
    markUiShellUpdateModalSeen,
    removeSymfonyToolbar,
    setupConsentInterceptor,
    setupProductAnalyticsInterceptor,
} from '@helpers/productanalytics-helpers';
import {
    AdminPageObjects,
    createNewAdminPageContext,
    loginToAdministration,
    AdminUser,
} from '@contena-ag/acceptance-test-suite';
import { satisfies } from 'compare-versions';

const TRACKING_EVENT_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';
const MONTHS_AHEAD = 3;

test(
    'Visual: Administration data sharing consent modal',
    { tag: '@Visual' },
    async ({ TestDataService, browser, ChannelBaseConfig, InstanceMeta }) => {
        test.skip(
            satisfies(InstanceMeta.version, '<6.7.9.0'),
            'Data sharing consent modal only available since version 6.7.9.0',
        );

        const page: Page = await createNewAdminPageContext(browser, ChannelBaseConfig);
        const AdminDataSharingConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);
        const AdminDashboard = new AdminPageObjects['Dashboard'](page);

        await page.clock.install({ time: new Date(new Date().setMonth(new Date().getMonth() + MONTHS_AHEAD)) });
        await markUiShellUpdateModalSeen(page);

        await test.step('Modify blog analytics API and consent API requests.', async () => {
            const { trackingEventHandler } = setupProductAnalyticsInterceptor();
            const { consentHandler } = setupConsentInterceptor();

            await page.route(`**/${TRACKING_EVENT_ENDPOINT}**`, trackingEventHandler);
            await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
        });

        await test.step('Login to contena administration.', async () => {
            const admin_user: AdminUser = await TestDataService.createUser();

            await loginToAdministration(page, admin_user, TestDataService.AdminApiClient);
            await removeSymfonyToolbar(page);
        });

        await test.step('Creates a screenshot of data sharing consent modal.', async () => {
            await setViewport(AdminDataSharingConsentModal.page, {
                contentHeight: 2646,
                waitForSelector: AdminDataSharingConsentModal.shareStoreDataCheckbox,
            });
            await replaceElements(AdminDashboard.page, [
                AdminDashboard.welcomeHeadline,
                AdminDashboard.welcomeMessage,
                AdminDashboard.statisticsDateRange,
            ]);
            await hideElements(AdminDashboard.page, [AdminDashboard.statisticsChart]);

            await assertScreenshot(AdminDataSharingConsentModal.page, 'Modal-Data-Sharing-Consent.png');
        });
    },
);
