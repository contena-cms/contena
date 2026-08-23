import { createPinia, setActivePinia } from 'pinia';
import type { AdminMenuStore } from './admin-menu.store';
import type { ModuleManifest } from '../../core/factory/module.factory';

type NavigationEntry = Exclude<ModuleManifest['navigation'], undefined>[number];

function pathOnlyEntry(path: string): NavigationEntry {
    return { path } as NavigationEntry;
}

describe('admin-menu.store', () => {
    let store: AdminMenuStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Contena.Store.get('adminMenu');
    });

    afterEach(() => {
        localStorage.removeItem('contena-admin-menu-expanded');
        localStorage.removeItem('ct-admin-menu-sidebar-expanded');
    });

    it('has initial state', () => {
        expect(store.isExpanded).toBe(true);
        expect(localStorage.getItem('ct-admin-menu-sidebar-expanded')).toBeNull();
        expect(store.expandedEntries).toStrictEqual([]);
        expect(store.adminModuleNavigation).toStrictEqual([]);
    });

    it('expands a menu entry with `expandMenuEntry`', () => {
        store.expandMenuEntry({ id: 'test' });
        expect(store.expandedEntries).toStrictEqual([{ id: 'test' }]);
    });

    it('keys entries without an id by their path', () => {
        store.expandMenuEntry(pathOnlyEntry('ct.first.index'));
        store.expandMenuEntry(pathOnlyEntry('ct.second.index'));

        store.collapseMenuEntry(pathOnlyEntry('ct.first.index'));

        expect(store.expandedEntries).toStrictEqual([{ path: 'ct.second.index' }]);
    });

    it('does not expand the same menu entry twice', () => {
        store.expandMenuEntry({ id: 'test' });
        store.expandMenuEntry({ id: 'test' });

        expect(store.expandedEntries).toStrictEqual([{ id: 'test' }]);
    });

    it('collapses all menu entries with `clearExpandedMenuEntries`', () => {
        store.expandMenuEntry({ id: 'test' });
        expect(store.expandedEntries).toStrictEqual([{ id: 'test' }]);

        store.clearExpandedMenuEntries();
        expect(store.expandedEntries).toStrictEqual([]);
    });

    it('collapses a menu entry with `collapseMenuEntry`', () => {
        store.expandMenuEntry({ id: 'test1' });
        store.expandMenuEntry({ id: 'test2' });
        expect(store.expandedEntries).toContainEqual({ id: 'test1' });
        expect(store.expandedEntries).toContainEqual({ id: 'test2' });

        store.collapseMenuEntry({ id: 'test1' });
        expect(store.expandedEntries).not.toContainEqual({ id: 'test1' });
        expect(store.expandedEntries).toContainEqual({ id: 'test2' });

        store.collapseMenuEntry({ id: 'test2' });
        expect(store.expandedEntries).not.toContainEqual({ id: 'test1' });
        expect(store.expandedEntries).not.toContainEqual({ id: 'test2' });
    });

    it('collapses the sidebar with `collapseSidebar`', () => {
        expect(store.isExpanded).toBe(true);
        expect(localStorage.getItem('ct-admin-menu-sidebar-expanded')).toBeNull();

        store.collapseSidebar();
        expect(store.isExpanded).toBe(false);
        expect(localStorage.getItem('ct-admin-menu-sidebar-expanded')).toBe('false');
    });

    it('expands the sidebar with `expandSidebar`', () => {
        store.collapseSidebar();
        expect(store.isExpanded).toBe(false);
        expect(localStorage.getItem('ct-admin-menu-sidebar-expanded')).toBe('false');

        store.expandSidebar();
        expect(store.isExpanded).toBe(true);
        expect(localStorage.getItem('ct-admin-menu-sidebar-expanded')).toBe('true');
    });
});
