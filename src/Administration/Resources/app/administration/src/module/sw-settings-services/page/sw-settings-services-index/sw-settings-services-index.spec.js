import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import {
    MtModal,
    MtModalClose,
    MtModalAction,
    MtModalTrigger,
    MtModalRoot,
    MtButton,
    MtBanner,
} from '@contena-ag/meteor-component-library';
import SwSettingsServicesIndex from './index';
import { useContenaServicesStore } from '../../store/contena-services.store';
import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const useContenaServicesStore = require('../../store/contena-services.store').useContenaServicesStore;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useContenaServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Contena.Service('contenaServicesService').acceptRevision(revision);
            _reloadPageMock();
        },
        async revokePermissions() {
            await Contena.Service('contenaServicesService').revokePermissions();
            _reloadPageMock();
        },
        _reloadPage: _reloadPageMock,
    };
});

describe('/src/module/sw-setting-services/page/sw-settings-services-index', () => {
    beforeAll(() => {
        Contena.Service().register('serviceRegistryClient', () => ({
            getCurrentRevision: jest.fn(async () => ({
                'latest-revision': '2025-06-25',
                'available-revisions': [
                    {
                        revision: '2025-06-25',
                        links: {
                            'feedback-url': 'https://contena.cn/feedback',
                            'docs-url': 'https://docs.contena.cn/services',
                            'tos-url': 'https://contena.cn/agb',
                        },
                    },
                ],
            })),
        }));

        Contena.Service().register('contenaServicesService', () => ({
            getInstalledServices: jest.fn(async () => [
                {
                    id: 'service-id',
                    active: true,
                    name: 'first-service-name',
                    label: 'First Service',
                    requirements: ['service_consent'],
                },
                {
                    id: 'service-id-2',
                    active: true,
                    name: 'second-service-name',
                    label: 'Second Service',
                    requirements: ['contena_account'],
                },
            ]),
            getServicesContext: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: {
                    identifier: 'revision-id',
                    revision: '2025-06-25',
                    consentingUserId: 'admin_user-id',
                    grantedAt: '2025-07-08',
                },
            })),
            acceptRevision: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: {
                    identifier: 'revision-id',
                    revision: '2025-06-25',
                    consentingUserId: 'admin_user-id',
                    grantedAt: '2025-07-08',
                },
            })),
            revokePermissions: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: null,
            })),
            enableAllServices: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: null,
            })),
        }));
    });

    async function mountPage() {
        const pinia = createPinia();
        setActivePinia(pinia);
        useContenaServicesStore();

        return mount(SwSettingsServicesIndex, {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                    },
                    'sw-settings-services-service-card': true,
                    'mt-modal': MtModal,
                    'mt-modal-close': MtModalClose,
                    'mt-modal-action': MtModalAction,
                    'mt-modal-trigger': MtModalTrigger,
                    'mt-modal-root': MtModalRoot,
                    'sw-extension-component-section': true,
                },
                plugins: [pinia],
            },
        });
    }

    it('shows installed services', async () => {
        const page = await mountPage();
        await flushPromises();

        const hero = page.getComponent(SwSettingsServicesHero);

        expect(hero.props('documentationLink')).toBe('https://docs.contena.cn/services');
        expect(hero.props('feedbackLink')).toBe('https://contena.cn/feedback');

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findComponent(MtBanner).exists()).toBe(false);

        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(2);

        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(true);
        expect(page.findComponent(SwSettingsServicesDeactivateModal).exists()).toBe(true);
    });

    it('passes Contena Account services to the global action modals', async () => {
        const page = await mountPage();
        await flushPromises();

        expect(page.getComponent(SwSettingsServicesRevokePermissionsModal).props('servicesWithAccountRequirement')).toEqual([
            {
                name: 'second-service-name',
                label: 'Second Service',
            },
        ]);
        expect(page.getComponent(SwSettingsServicesDeactivateModal).props('servicesWithAccountRequirement')).toEqual([
            {
                name: 'second-service-name',
                label: 'Second Service',
            },
        ]);
    });

    it('shows correct links in the footer', async () => {
        const page = await mountPage();
        await flushPromises();

        const footerLinks = page.findAll('.sw-settings-services__footer a');

        expect(footerLinks).toHaveLength(2);

        const [documentationLink, tosLink] = footerLinks;

        expect(documentationLink.attributes('href')).toBe('https://docs.contena.cn/services');
        expect(tosLink.attributes('href')).toBe('https://contena.cn/agb');
    });

    it('can grant permissions', async () => {
        Contena.Service('contenaServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: false,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);

        const grantPermissionsCard = page.getComponent(SwSettingsServicesGrantPermissionsCard);

        await grantPermissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('can revoke permissions', async () => {
        const page = await mountPage();
        await flushPromises();

        const revokePermissionsModal = page.getComponent(SwSettingsServicesRevokePermissionsModal);

        await revokePermissionsModal.getComponent(MtModalTrigger).trigger('click');
        await revokePermissionsModal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('does not show grant permissions card if services are deactivated', async () => {
        Contena.Service('contenaServicesService').getInstalledServices.mockImplementationOnce(async () => []);

        Contena.Service('contenaServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(0);
    });

    it('shows installed services that remain available when services are deactivated', async () => {
        Contena.Service('contenaServicesService').getInstalledServices.mockImplementationOnce(async () => [
            {
                id: 'gmv-service-id',
                active: true,
                name: 'gmv',
                label: 'GMV',
                requirements: ['contena_account'],
            },
        ]);

        Contena.Service('contenaServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionsConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        const activateBanner = page.getComponent(MtBanner);

        expect(activateBanner.props('variant')).toBe('attention');
        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);
        expect(page.findComponent(SwSettingsServicesDeactivateModal).exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);
    });

    it('can activate services', async () => {
        Contena.Service('contenaServicesService').getInstalledServices.mockImplementationOnce(async () => []);

        Contena.Service('contenaServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        const activateBanner = page.getComponent(MtBanner);

        await activateBanner.getComponent(MtButton).trigger('click');
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(0);
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);
    });

    it('shows error banner', async () => {
        Contena.Service('contenaServicesService').getInstalledServices.mockImplementationOnce(async () => {
            throw new Error('failed loading services');
        });

        const page = await mountPage();
        await flushPromises();

        const errorBanner = page.getComponent(MtBanner);
        expect(errorBanner.props('variant')).toBe('critical');
        expect(errorBanner.text()).toContain('failed loading services');
    });
});
