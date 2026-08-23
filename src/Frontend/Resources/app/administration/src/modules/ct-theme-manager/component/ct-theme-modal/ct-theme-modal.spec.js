import { shallowMount } from '@vue/test-utils';
import { createMemoryHistory, createRouter, routeLocationKey, routerKey } from 'vue-router';
import swThemeModal from './index';

Contena.Component.register('ct-theme-modal', swThemeModal);

describe('ct-theme-modal', () => {
    async function createWrapper({ repositorySearch = null, selectedThemeId = null } = {}) {
        const component = await Contena.Component.build('ct-theme-modal');
        const themeRepository = {
            search: repositorySearch || jest.fn(() => Promise.resolve({ total: 0, length: 0 })),
        };
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    name: 'ct.theme.manager.index',
                    path: '/themes',
                    component: { template: '<div />' },
                },
            ],
        });
        await router.push({ name: 'ct.theme.manager.index' });

        return shallowMount(component, {
            props: {
                selectedThemeId,
            },
            global: {
                stubs: {
                    'mt-modal-root': true,
                    'mt-modal': true,
                    'mt-search': true,
                    'mt-loader': true,
                    'ct-theme-list-item': true,
                    'mt-button': true,
                    'mt-checkbox': true,
                },
                provide: {
                    [routerKey]: router,
                    [routeLocationKey]: router.currentRoute.value,
                    repositoryFactory: {
                        create: () => themeRepository,
                    },
                    feature: {},
                    searchRankingService: {
                        getSearchFieldsByEntity: () => ({}),
                    },
                },
            },
        });
    }

    it('sets selected theme on created', async () => {
        const wrapper = await createWrapper({ selectedThemeId: 'theme-id' });

        expect(wrapper.vm.selected).toBe('theme-id');
    });

    it('loads theme list and updates state', async () => {
        const searchSpy = jest.fn(() => Promise.resolve({ total: 2, length: 2 }));
        const wrapper = await createWrapper({ repositorySearch: searchSpy });

        const result = await wrapper.vm.getList();

        expect(searchSpy).toHaveBeenCalledWith(expect.any(Object), Contena.Context.api);
        expect(result).toEqual({ total: 2, length: 2 });
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.themes).toEqual({ total: 2, length: 2 });
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('clears loading state when list fetch fails', async () => {
        const wrapper = await createWrapper({
            repositorySearch: jest.fn(() => Promise.reject(new Error('fail'))),
        });

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('selects and emits modal theme selection', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.selected = 'theme-id';
        wrapper.vm.selectLayout();

        expect(wrapper.emitted('modal-theme-select')[0]).toEqual(['theme-id']);
        expect(wrapper.emitted('modal-close')).toBeDefined();
        expect(wrapper.vm.selected).toBeNull();
        expect(wrapper.vm.term).toBeNull();
    });

    it('updates selected item', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.selectItem('theme-id');

        expect(wrapper.vm.selected).toBe('theme-id');
    });

    it('search resets page and triggers list loading', async () => {
        const searchSpy = jest.fn(() => Promise.resolve({ total: 0, length: 0 }));
        const wrapper = await createWrapper({ repositorySearch: searchSpy });
        searchSpy.mockClear();

        wrapper.vm.page = 3;
        wrapper.vm.onSearch('Foo');
        await flushPromises();

        expect(wrapper.vm.term).toBe('Foo');
        expect(wrapper.vm.page).toBe(1);
        expect(searchSpy).toHaveBeenCalled();
    });
});
