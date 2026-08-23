import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { globSync } from 'glob';

const mainSource = readFileSync(resolve(__dirname, 'main.ts'), 'utf8');
const repositorySource = readFileSync(resolve(__dirname, 'init/repository.init.ts'), 'utf8');
const applicationSource = readFileSync(resolve(__dirname, '../core/application.ts'), 'utf8');
const contenaSource = readFileSync(resolve(__dirname, '../core/contena.ts'), 'utf8');
const roleDetailSource = readFileSync(
    resolve(__dirname, '../module/ct-permissions/page/ct-permissions-role-detail/index.js'),
    'utf8',
);
const userDetailSource = readFileSync(resolve(__dirname, '../module/ct-users/page/ct-users-user-detail/index.js'), 'utf8');
const loginSource = readFileSync(resolve(__dirname, '../module/ct-login/view/ct-login-login/index.ts'), 'utf8');
const adminShellSource = readFileSync(resolve(__dirname, 'component/structure/ct-admin/ct-admin.vue'), 'utf8');
const adminShellConfigSource = readFileSync(resolve(__dirname, 'component/structure/ct-admin/index.ts'), 'utf8');
const componentIndexSource = readFileSync(resolve(__dirname, 'component/index.ts'), 'utf8');
const storeInitializerSource = readFileSync(resolve(__dirname, 'init-pre/store.init.ts'), 'utf8');
const initializerIndexSource = readFileSync(resolve(__dirname, 'init/index.ts'), 'utf8');
const desktopSource = readFileSync(resolve(__dirname, 'component/structure/ct-desktop/ct-desktop.vue'), 'utf8');
const pageSource = readFileSync(resolve(__dirname, 'component/structure/ct-page/ct-page.vue'), 'utf8');
const meteorPageSource = readFileSync(resolve(__dirname, 'component/meteor/ct-meteor-page/ct-meteor-page.vue'), 'utf8');

const forbiddenServiceProviders = [
    'appAclService',
    'appCmsService',
    'customEntityDefinitionService',
    'licenseViolationService',
    'productStreamConditionService',
    'productTypeService',
    'ruleConditionDataProviderService',
    'contenaDiscountCampaignService',
];

const forbiddenLoginListeners = [
    'addCustomerGroupRegistrationListener',
    'addContenaUpdatesListener',
];

const forbiddenRemoteInitializers = [
    'actionButton',
    'actions',
    'cms',
    'consent',
    'context',
    'extensionComponentSections',
    'extensionDataHandling',
    'inAppPurchaseCheckout',
    'mainModules',
    'mediaModal',
    'menu',
    'modals',
    'notification',
    'settingItems',
    'sidebar',
    'tabs',
    'teaserPopover',
    'telemetry',
    'topbarButton',
    'window',
];

const forbiddenRemoteComponents = [
    'ct-app-action-button',
    'ct-app-actions',
    'ct-app-topbar-button',
    'ct-app-topbar-sidebar',
    'ct-app-wrong-app-url-modal',
    'ct-hidden-iframes',
    'ct-extension-component-section',
    'ct-extension-teaser-popover',
    'ct-extension-teaser-sales-channel',
    'ct-iframe-renderer',
    'ct-in-app-purchase-checkout',
    'ct-modals-renderer',
    'ct-request-consent-modal',
    'ct-sidebar-renderer',
];

const forbiddenRemoteSourcePaths = [
    'component/app/ct-app-actions/index.js',
    'component/extension-api/ct-iframe-renderer/index.ts',
    'component/extension-api/ct-extension-component-section/index.ts',
    'component/structure/ct-hidden-iframes/index.js',
    'component/structure/ct-in-app-purchase-checkout/index.ts',
    'component/structure/ct-modals-renderer/index.ts',
    'component/structure/ct-request-consent-modal/index.ts',
    'component/structure/ct-sidebar-renderer/index.ts',
    '../core/consent/sdk-handler.ts',
    'store/action-buttons.store.ts',
    'store/extension-component-sections.store.ts',
    '../core/in-app-purchase.ts',
    '../core/service/api/app-modules.service.ts',
    '../core/extension-api.ts',
    '../core/service/extension-api-data.service.ts',
    '../module/ct-cms/blocks/app/app-renderer/index.ts',
    '../module/ct-cms/elements/location-renderer/index.ts',
    '../module/ct-flow/component/modals/ct-flow-app-action-modal/index.js',
    'plugin/meteor-sdk-data.plugin.ts',
    'store/extensions.store.ts',
    'store/extension-sdk-module.store.ts',
    'store/in-app-purchase-checkout.store.ts',
    'store/main-module.store.ts',
    'store/marketing.store.ts',
    'store/menu-item.store.ts',
    'store/modals.store.ts',
    'store/sdk-location.store.ts',
    'store/sidebar.store.ts',
    'store/contena-apps.store.ts',
    'store/tabs.store.ts',
    'store/teaser-popover.store.ts',
    'store/topbar-button.store.ts',
];

describe('Contena core Administration services', () => {
    it.each(forbiddenServiceProviders)('does not register the excluded %s provider', (provider) => {
        expect(mainSource).not.toContain(`addServiceProvider('${provider}'`);
    });

    it.each(forbiddenLoginListeners)('does not register the excluded %s login listener', (listener) => {
        expect(mainSource).not.toContain(listener);
    });

    it('loads DAL definitions without deriving App or CMS administration metadata', () => {
        expect(repositorySource).not.toContain('customEntityDefinitionService');
        expect(repositorySource).not.toContain('cmsPageTypeService');
    });

    it('does not load App permissions into native role management', () => {
        expect(roleDetailSource).not.toContain('appAclService');
    });

    it('keeps remote App data channels out of native Plugin and user management', () => {
        expect(applicationSource).not.toContain('injectIframe');
        expect(contenaSource).not.toContain('ExtensionAPI');
        expect(storeInitializerSource).not.toContain('app/store/extensions.store');
        expect(adminShellConfigSource).not.toContain('ExtensionAPI');
        expect(roleDetailSource).not.toContain('ExtensionAPI');
        expect(userDetailSource).not.toContain('ExtensionAPI');
    });

    it('does not publish Administration data through the removed App API', () => {
        const extensionApiConsumers = globSync(resolve(__dirname, '../**/*.{js,ts}'))
            .filter((file) => !file.includes('.spec.'))
            .filter((file) => readFileSync(file, 'utf8').includes('Contena.ExtensionAPI'));

        expect(extensionApiConsumers).toStrictEqual([]);
    });

    it('does not ship remote App CMS renderers', () => {
        const iframeConsumers = globSync(resolve(__dirname, '../**/*.{js,ts,vue}'))
            .filter((file) => !file.includes('.spec.'))
            .filter((file) => readFileSync(file, 'utf8').includes('ct-iframe-renderer'));

        expect(iframeConsumers).toStrictEqual([]);
    });

    it('does not ship remote App Flow actions', () => {
        const forbiddenAppFlowActionReferences = [
            'app_flow_action',
            'propsAppFlowAction',
            'ct-flow-app-action-modal',
            'getSelectedAppAction',
            'setAppActions',
        ];
        const flowSources = globSync(resolve(__dirname, '../module/ct-flow/**/*.{js,ts,vue}')).filter(
            (file) => !file.includes('.spec.'),
        );

        forbiddenAppFlowActionReferences.forEach((reference) => {
            const consumers = flowSources.filter((file) => readFileSync(file, 'utf8').includes(reference));

            expect(consumers).toStrictEqual([]);
        });
    });

    it('does not ship remote App Flow script conditions', () => {
        const forbiddenAppScriptConditionReferences = [
            'app_script_condition',
            'appScriptConditionRepository',
            'addScriptConditions',
        ];
        const flowSources = globSync(resolve(__dirname, '../module/ct-flow/**/*.{js,ts,vue}')).filter(
            (file) => !file.includes('.spec.'),
        );

        forbiddenAppScriptConditionReferences.forEach((reference) => {
            const consumers = flowSources.filter((file) => readFileSync(file, 'utf8').includes(reference));

            expect(consumers).toStrictEqual([]);
        });
    });

    it('does not load Store license enforcement into the generic shell or login', () => {
        expect(loginSource).not.toContain('licenseViolationService');
        expect(adminShellSource).not.toContain('ct-license-violation');
        expect(componentIndexSource).not.toContain('ct-license-violation');
        expect(storeInitializerSource).not.toContain('license-violation.store');
    });

    it.each(forbiddenRemoteInitializers)('does not start the remote App %s initializer', (initializer) => {
        expect(initializerIndexSource).not.toContain(`${initializer}:`);
    });

    it.each(forbiddenRemoteComponents)('does not register the remote App component %s', (component) => {
        expect(componentIndexSource).not.toContain(`'${component}'`);
    });

    it.each(forbiddenRemoteSourcePaths)('does not ship the remote App source path %s', (sourcePath) => {
        expect(existsSync(resolve(__dirname, sourcePath))).toBe(false);
    });

    it('keeps remote App surfaces out of the generic shell and page chrome', () => {
        const shellSources = [
            adminShellSource,
            desktopSource,
            pageSource,
            meteorPageSource,
        ];

        forbiddenRemoteComponents.forEach((component) => {
            shellSources.forEach((source) => expect(source).not.toContain(`<${component}`));
        });
    });
});
