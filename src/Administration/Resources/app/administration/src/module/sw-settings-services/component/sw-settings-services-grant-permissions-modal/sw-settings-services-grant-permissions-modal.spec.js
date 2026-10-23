import { mount } from '@vue/test-utils';
import { MtModal, MtModalClose, MtModalAction, MtModalTrigger, MtModalRoot } from '@contena-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsModal from './index';
import { useContenaServicesStore } from '../../store/contena-services.store';
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
        revokePermissions: jest.fn(),
        _reloadPage: _reloadPageMock,
    };
});

const createWrapper = async () => {
    return mount(SwSettingsServicesGrantPermissionsModal, {
        global: {
            stubs: {
                'mt-modal': MtModal,
                'mt-modal-close': MtModalClose,
                'mt-modal-action': MtModalAction,
                'mt-modal-trigger': MtModalTrigger,
                'mt-modal-root': MtModalRoot,
            },
        },
    });
};

describe('src/module/sw-settings-services/component/sw-settings-services-grant-permissions-modal', () => {
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
            acceptRevision: jest.fn(),
        }));
    });

    it('can be opened by the pinia store', async () => {
        const contenaServicesStore = useContenaServicesStore();
        expect(contenaServicesStore.revisions).toBeNull();

        const grantPermissionsModal = await createWrapper();
        const modal = grantPermissionsModal.getComponent(MtModal);

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);

        contenaServicesStore.showGrantPermissionsModal = true;
        await flushPromises();

        expect(contenaServicesStore.revisions).toEqual({
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
        });

        await modal.getComponent(MtModalClose).trigger('click');

        expect(modal.findComponent(MtModalClose).exists()).toBe(false);
        expect(contenaServicesStore.showGrantPermissionsModal).toBe(false);
    });

    it('sends grant permissions request', async () => {
        const contenaServicesStore = useContenaServicesStore();
        const notificationStore = Contena.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

        contenaServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(Contena.Service('contenaServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('shows error notification if no revision is available', async () => {
        const contenaServicesStore = useContenaServicesStore();
        const notificationStore = Contena.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification');

        const grantPermissionsModal = await createWrapper();

        contenaServicesStore.showGrantPermissionsModal = true;
        await flushPromises();
        contenaServicesStore.revisions = null;

        const modal = grantPermissionsModal.getComponent(MtModal);
        await modal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'No revision available',
        });
        expect(Contena.Service('contenaServicesService').acceptRevision).not.toHaveBeenCalled();
        expect(permissionsComposable._reloadPage).not.toHaveBeenCalled();
    });
});
