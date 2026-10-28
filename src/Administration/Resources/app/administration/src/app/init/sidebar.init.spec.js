import { ui } from '@contena-ag/meteor-admin-sdk';
import initializeSidebar from './sidebar.init';

describe('src/app/init/sidebar.init', () => {
    beforeAll(() => {
        // Execute the initializeSidebar function
        initializeSidebar();
    });

    beforeEach(() => {
        // Reset the sidebar store
        Contena.Store.get('sidebar').sidebars = [];
        Contena.Store.get('sidebar').closingSidebar = null;

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

    it('should handle uiSidebarAdd', async () => {
        // Check that sidebar store is empty
        expect(Contena.Store.get('sidebar').sidebars).toEqual([]);

        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Contena.Store.get('sidebar').sidebars).toHaveLength(1);
        expect(Contena.Store.get('sidebar').sidebars[0]).toEqual({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
            active: false,
            baseUrl: '',
        });
    });

    it('should handle uiSidebarClose', async () => {
        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Contena.Store.get('sidebar').sidebars).toHaveLength(1);

        // Check that sidebar is not active
        expect(Contena.Store.get('sidebar').sidebars[0].active).toBe(false);

        // Open the sidebar
        Contena.Store.get('sidebar').sidebars[0].active = true;

        jest.useFakeTimers();

        // Close the sidebar
        await ui.sidebar.close({
            locationId: 'test-sidebar',
        });

        // The close plays the closing animation before deactivating
        expect(Contena.Store.get('sidebar').closingSidebar).toBe('test-sidebar');
        jest.advanceTimersByTime(400);
        jest.useRealTimers();

        // Check that sidebar is not active
        expect(Contena.Store.get('sidebar').sidebars[0].active).toBe(false);
    });

    it('should handle uiSidebarSetActive', async () => {
        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Contena.Store.get('sidebar').sidebars).toHaveLength(1);

        // Check that sidebar is not active
        expect(Contena.Store.get('sidebar').sidebars[0].active).toBe(false);

        // Activate the sidebar
        await ui.sidebar.setActive({
            locationId: 'test-sidebar',
        });

        // Check that sidebar is not active
        expect(Contena.Store.get('sidebar').sidebars[0].active).toBe(true);
    });

    it('should handle uiSidebarRemove', async () => {
        // Add a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Check that sidebar store has the added sidebar
        expect(Contena.Store.get('sidebar').sidebars).toHaveLength(1);

        // Remove the sidebar
        await ui.sidebar.remove({
            locationId: 'test-sidebar',
        });

        // Check that sidebar store is empty
        expect(Contena.Store.get('sidebar').sidebars).toEqual([]);
    });
});
