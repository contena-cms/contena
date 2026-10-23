/**
 * @sw-package checkout
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import ContenaService from 'src/module/sw-extension/service/contena-extension.service';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
export { default as selectMtSelectOptionByText } from '../../../../../../test/_helper_/select-mt-select-by-text';

export const routes = [
    {
        name: 'sw.extension.my-extensions.listing.app',
        path: '/sw/extension/my-extensions/listing/app',
        query: {},
        component: {},
    },
    {
        name: 'sw.extension.my-extensions.listing.theme',
        path: '/sw/extension/my-extensions/listing/theme',
        query: {},
        component: {},
    },
];

export const contenaService = new ContenaService({}, {}, {}, {});
contenaService.updateExtensionData = jest.fn();
contenaService.installExtension = jest.fn(() => Promise.resolve());
contenaService.installAndActivateExtension = jest.fn(() => Promise.resolve());
contenaService.activateExtension = jest.fn(() => Promise.resolve());
contenaService.deactivateExtension = jest.fn(() => Promise.resolve());
contenaService.uninstallExtension = jest.fn(() => Promise.resolve());
contenaService.updateExtension = jest.fn(() => Promise.resolve());

export const extensionStoreActionService = {
    downloadExtension: jest.fn(() => Promise.resolve()),
};

// The page uses the sw-extension-error mixin, which resolves this service to map error responses.
if (!Contena.Service().list().includes('extensionErrorService')) {
    Contena.Service().register('extensionErrorService', () => ({
        handleErrorResponse: jest.fn(() => []),
    }));
}

// Consent error the backend throws on update when an extension requires new permissions.
export function consentError(deltas = { permissions: {}, domains: [] }) {
    return {
        response: {
            data: {
                errors: [
                    {
                        code: 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION',
                        meta: { parameters: { deltas } },
                    },
                ],
            },
        },
    };
}

export function setMyExtensions(extensions) {
    Contena.Store.get('contenaExtensions').setMyExtensions(extensions);
}

export function makeCardStub({ emits = [] } = {}) {
    return {
        template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
        props: ['extension', 'selected', 'bulkLoading'],
        emits,
    };
}

export async function createWrapper({ aclCan = () => true, cardStub, query = {} } = {}) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    await router.push({ ...routes[0], query });
    await router.isReady();

    return mount(
        await wrapTestComponent('sw-extension-my-extensions-listing', {
            sync: true,
        }),
        {
            global: {
                plugins: [router],
                // The page declares the sw-extension-error mixin by name, resolve it explicitly for the test.
                mixins: [Contena.Mixin.getByName('sw-extension-error')],
                stubs: {
                    'router-link': true,
                    'sw-self-maintained-extension-card': cardStub ?? {
                        template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
                        props: ['extension'],
                    },
                    'sw-extension-bulk-actions-bar': await wrapTestComponent('sw-extension-bulk-actions-bar', {
                        sync: true,
                    }),
                    'sw-pagination': await wrapTestComponent('sw-pagination', {
                        sync: true,
                    }),
                    'sw-field': true,
                    'sw-extension-my-extensions-listing-controls': await wrapTestComponent(
                        'sw-extension-my-extensions-listing-controls',
                        { sync: true },
                    ),

                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                    'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-skeleton': true,
                    'sw-external-link': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                    'sw-extension-component-section': true,
                    'sw-extension-permissions-modal': {
                        template: '<div class="sw-extension-permissions-modal" />',
                        props: [
                            'permissions',
                            'domains',
                            'title',
                            'description',
                            'actionLabel',
                            'extensionLabel',
                        ],
                    },
                    'sw-extension-bulk-uninstall-modal': {
                        template: '<div class="sw-extension-bulk-uninstall-modal" />',
                        props: ['extensions', 'isLoading'],
                    },
                    'sw-extension-bulk-deactivation-modal': {
                        template: '<div class="sw-extension-bulk-deactivation-modal" />',
                        props: ['extensions', 'isLoading'],
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {};
                        },
                    },
                    contenaExtensionService: contenaService,
                    extensionStoreActionService,
                    cacheApiService: {
                        clear: jest.fn(() => Promise.resolve()),
                    },
                    acl: {
                        can: aclCan,
                    },
                },
            },
            attachTo: document.body,
        },
    );
}

/**
 * Registers the store setup and mock resets every listing spec relies on.
 */
export function setupListingHooks() {
    beforeAll(() => {
        Contena.Store.get('contenaExtensions').setMyExtensions([{ name: 'Test', installedAt: null }]);

        if (Contena.Store.get('context')) {
            Contena.Store.unregister('context');
        }

        Contena.Store.register({
            id: 'context',
            state: () => ({
                app: {
                    config: {
                        settings: {
                            appUrlReachable: true,
                        },
                    },
                },
                api: {
                    assetsPath: '/',
                },
            }),
        });
    });

    beforeEach(async () => {
        setMyExtensions([
            {
                name: 'Test',
                installedAt: null,
            },
        ]);

        Contena.Store.get('context').app.config.settings.disableExtensionManagement = false;
        Contena.Store.get('context').app.config.settings.appUrlReachable = true;

        contenaService.updateExtensionData.mockClear();
        contenaService.installExtension.mockClear();
        contenaService.installExtension.mockResolvedValue(undefined);
        contenaService.installAndActivateExtension.mockClear();
        contenaService.installAndActivateExtension.mockResolvedValue(undefined);
        contenaService.activateExtension.mockClear();
        contenaService.activateExtension.mockResolvedValue(undefined);
        contenaService.deactivateExtension.mockClear();
        contenaService.deactivateExtension.mockResolvedValue(undefined);
        contenaService.uninstallExtension.mockClear();
        contenaService.uninstallExtension.mockResolvedValue(undefined);
        contenaService.updateExtension.mockClear();
        contenaService.updateExtension.mockResolvedValue(undefined);
        extensionStoreActionService.downloadExtension.mockClear();
        extensionStoreActionService.downloadExtension.mockResolvedValue(undefined);
    });
}
