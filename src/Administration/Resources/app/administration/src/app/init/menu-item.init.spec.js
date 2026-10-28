/**
 * @sw-package framework
 */
import initMenuItems from 'src/app/init/menu-item.init';
import { ui } from '@contena-ag/meteor-admin-sdk';

describe('src/app/init/menu-item.init.ts', () => {
    beforeAll(() => {
        initMenuItems();
    });

    beforeEach(() => {
        Contena.Store.get('extensionSdkModules').modules = [];

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

        // Reset mocks
        jest.clearAllMocks();
    });

    it('should handle incoming menuItemAdd requests', async () => {
        await ui.menu.addMenuItem({
            label: 'Test item',
            locationId: 'your-location-id',
            displaySearchBar: true,
            displaySmartBar: true,
            parent: 'sw-catalogue',
        });

        expect(Contena.Store.get('extensionSdkModules').modules).toHaveLength(1);
    });

    it('should not handle requests when extension is not valid', async () => {
        Contena.Store.get('extensions').extensionsState = {};

        await expect(async () => {
            await ui.menu.addMenuItem({
                label: 'Test item',
                locationId: 'your-location-id',
                displaySearchBar: true,
                displaySmartBar: true,
                parent: 'sw-catalogue',
            });
        }).rejects.toThrow(new Error('Extension with the origin "" not found.'));

        expect(Contena.Store.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should not commit the extension when moduleID could not be generated', async () => {
        jest.spyOn(Contena.Store.get('extensionSdkModules'), 'addModule').mockImplementationOnce(() => {
            return Promise.resolve(null);
        });

        await ui.menu.addMenuItem({
            label: 'Test item',
            locationId: 'your-location-id',
            displaySearchBar: true,
            displaySmartBar: true,
            parent: 'sw-catalogue',
        });

        expect(Contena.Store.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should handle incoming menuCollapse/menuExpand requests', async () => {
        await ui.menu.collapseMenu();
        expect(Contena.Store.get('adminMenu').isExpanded).toBe(false);

        await ui.menu.expandMenu();
        expect(Contena.Store.get('adminMenu').isExpanded).toBe(true);
    });
});
