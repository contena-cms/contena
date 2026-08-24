import { createPinia, setActivePinia } from 'pinia';
import type { RouteTabsStore } from './route-tabs.store';
import './route-tabs.store';

function tab(key: string, pinned = false) {
    return { key, routeName: key, title: key, pinned };
}

describe('route-tabs.store', () => {
    let store: RouteTabsStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Contena.Store.get('routeTabs');
        store.tabs = [];
    });

    it('adds routes once and updates their translated title', () => {
        store.addTab(tab('/dashboard', true));
        store.addTab({ ...tab('/dashboard'), title: 'Home' });

        expect(store.tabs).toEqual([
            expect.objectContaining({ key: '/dashboard', title: 'Home', pinned: true, refreshKey: 0 }),
        ]);
    });

    it('increments the refresh key for the requested tab', () => {
        store.addTab(tab('/users'));

        store.refreshTab('/users');

        expect(store.tabs[0].refreshKey).toBe(1);
    });

    it('keeps pinned tabs and returns the adjacent route when closing the active tab', () => {
        store.addTab(tab('/dashboard', true));
        store.addTab(tab('/users'));
        store.addTab(tab('/settings'));

        expect(store.closeTab('/dashboard')).toBe('/dashboard');
        expect(store.closeTab('/users')).toBe('/settings');
        expect(store.tabs.map(({ key }) => key)).toEqual([
            '/dashboard',
            '/settings',
        ]);
    });

    it.each([
        [
            'left',
            [
                '/dashboard',
                '/users',
                '/settings',
            ],
        ],
        [
            'right',
            [
                '/dashboard',
                '/content',
                '/users',
            ],
        ],
        [
            'others',
            [
                '/dashboard',
                '/users',
            ],
        ],
        [
            'all',
            ['/dashboard'],
        ],
    ] as const)('closes %s tabs while retaining the dashboard', (mode, expectedKeys) => {
        store.addTab(tab('/dashboard', true));
        store.addTab(tab('/content'));
        store.addTab(tab('/users'));
        store.addTab(tab('/settings'));

        store.closeTabs(mode, '/users');

        expect(store.tabs.map(({ key }) => key)).toEqual(expectedKeys);
    });
});
