/**
 * @sw-package framework
 */
import initializeModal from 'src/app/init/modals.init';
import { ui } from '@contena-ag/meteor-admin-sdk';

describe('src/app/init/modals.init.ts', () => {
    beforeAll(() => {
        initializeModal();
    });

    beforeEach(() => {
        Contena.Store.get('modals').modals = [];

        Contena.Store.get('extensions').extensionsState = {};
        Contena.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });
    });

    it('should handle incoming uiModalOpen requests', async () => {
        await ui.modal.open({
            title: 'Your modal title',
            locationId: 'your-location-id',
            variant: 'large',
            showHeader: true,
            showFooter: false,
            closable: true,
            buttons: [
                {
                    label: 'Dispatch notification',
                    method: () => {
                        // method content
                    },
                },
                {
                    label: 'Close modal',
                    variant: 'primary',
                    method: () => {
                        ui.modal.close({
                            locationId: 'your-location-id',
                        });
                    },
                },
            ],
        });

        expect(Contena.Store.get('modals').modals).toHaveLength(1);
    });

    it('should handle incoming uiModalClose requests', async () => {
        await ui.modal.open({
            title: 'Your modal title',
            locationId: 'your-location-id',
            variant: 'large',
            showHeader: true,
            showFooter: false,
            closable: true,
            buttons: [
                {
                    label: 'Dispatch notification',
                    method: () => {
                        // method content
                    },
                },
                {
                    label: 'Close modal',
                    variant: 'primary',
                    method: () => {
                        ui.modal.close({
                            locationId: 'your-location-id',
                        });
                    },
                },
            ],
        });

        expect(Contena.Store.get('modals').modals).toHaveLength(1);

        await ui.modal.close({
            locationId: 'your-location-id',
        });

        expect(Contena.Store.get('modals').modals).toHaveLength(0);
    });

    it('should not handle requests when extension is not valid', async () => {
        Contena.Store.get('extensions').extensionsState = {};

        await expect(async () => {
            await ui.modal.open({
                title: 'Your modal title',
                locationId: 'your-location-id',
                variant: 'large',
                showHeader: true,
                showFooter: false,
                closable: true,
                buttons: [
                    {
                        label: 'Dispatch notification',
                        method: () => {
                            // method content
                        },
                    },
                    {
                        label: 'Close modal',
                        variant: 'primary',
                        method: () => {
                            ui.modal.close({
                                locationId: 'your-location-id',
                            });
                        },
                    },
                ],
            });
        }).rejects.toThrow(new Error('Extension with the origin "" not found.'));

        expect(Contena.Store.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should handle incoming uiModalUpdate requests and update the modal', async () => {
        // Open modal
        await ui.modal.open({
            title: 'Initial title',
            locationId: 'update-location-id',
            variant: 'small',
        });

        expect(Contena.Store.get('modals').modals[0].title).toBe('Initial title');

        // Update modal
        await ui.modal.update({
            locationId: 'update-location-id',
            title: 'Updated title',
            variant: 'large',
        });

        expect(Contena.Store.get('modals').modals[0].title).toBe('Updated title');
        expect(Contena.Store.get('modals').modals[0].variant).toBe('large');
    });

    it('should update the buttons when uiModalUpdate is called', async () => {
        await ui.modal.open({
            title: 'Button Modal',
            locationId: 'button-modal-id',
            buttons: [{ label: 'Old Button', method: () => {} }],
        });

        expect(Contena.Store.get('modals').modals[0].buttons).toHaveLength(1);
        expect(Contena.Store.get('modals').modals[0].buttons[0].label).toBe('Old Button');

        await ui.modal.update({
            locationId: 'button-modal-id',
            buttons: [
                { label: 'New Button', method: () => {} },
                { label: 'Another Button', method: () => {} },
            ],
        });

        expect(Contena.Store.get('modals').modals[0].buttons).toHaveLength(2);
        expect(Contena.Store.get('modals').modals[0].buttons[0].label).toBe('New Button');
        expect(Contena.Store.get('modals').modals[0].buttons[1].label).toBe('Another Button');
    });
});
