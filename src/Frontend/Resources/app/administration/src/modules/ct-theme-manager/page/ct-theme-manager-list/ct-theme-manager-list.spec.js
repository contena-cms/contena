import { shallowMount } from '@vue/test-utils';
import { createMemoryHistory, createRouter, routeLocationKey, routerKey } from 'vue-router';
import ctThemeManagerList from './index';

Contena.Component.register('ct-theme-manager-list', ctThemeManagerList);

describe('ct-theme-manager-list', () => {
    async function createWrapper({ aclCan = true, searchResult = null } = {}) {
        const component = await Contena.Component.build('ct-theme-manager-list');
        const themes =
            searchResult ||
            (() => {
                const result = [{ id: 'theme-id', channels: [] }];
                result.total = 1;
                return result;
            })();

        const themeRepository = {
            search: jest.fn(() => Promise.resolve(themes)),
            save: jest.fn(() => Promise.resolve()),
        };
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    name: 'ct.theme.manager.index',
                    path: '/themes/:id?',
                    component: { template: '<div />' },
                },
                {
                    name: 'ct.theme.manager.detail',
                    path: '/themes/detail/:id',
                    component: { template: '<div />' },
                },
            ],
        });
        await router.push({ name: 'ct.theme.manager.index', params: { id: 'channel-id' } });

        return shallowMount(component, {
            global: {
                stubs: {
                    'ct-card-view': true,
                    'ct-media-modal-v2': true,
                    'ct-page': true,
                    'ct-theme-list-item': true,
                    'mt-button': true,
                    'mt-card': true,
                    'mt-context-button': true,
                    'mt-context-menu-item': true,
                    'mt-data-table': true,
                    'mt-icon': true,
                    'mt-modal': true,
                    'mt-modal-root': true,
                    'mt-pagination': true,
                    'mt-search': true,
                    'mt-select': true,
                    'mt-skeleton-bar': true,
                    'mt-text-field': true,
                    'router-link': true,
                },
                provide: {
                    [routerKey]: router,
                    [routeLocationKey]: router.currentRoute.value,
                    repositoryFactory: {
                        create: () => themeRepository,
                    },
                    acl: {
                        can: jest.fn(() => aclCan),
                    },
                    searchRankingService: {
                        isValidTerm: () => true,
                        getSearchFieldsByEntity: () => ({}),
                    },
                },
                mocks: {
                    $createTitle: jest.fn(() => 'title'),
                },
            },
        });
    }

    it('loads theme list and updates state', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.getList();

        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.themes).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('clears loading state when list fetch fails', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.search.mockRejectedValueOnce(new Error('fail'));

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('search resets list and normalizes empty term', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.search.mockClear();

        wrapper.vm.onSearch('');
        await flushPromises();

        expect(wrapper.vm.term).toBeNull();
        expect(wrapper.vm.themeRepository.search).toHaveBeenCalled();
    });

    it('changes sorting and resets list', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.search.mockClear();

        wrapper.vm.onSortingChanged('updatedAt:ASC');
        await flushPromises();

        expect(wrapper.vm.sortBy).toBe('updatedAt');
        expect(wrapper.vm.sortDirection).toBe('ASC');
        expect(wrapper.vm.themeRepository.search).toHaveBeenCalled();
    });

    it('toggles list mode and updates limit', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.search.mockClear();

        wrapper.vm.listMode = 'grid';
        wrapper.vm.onListModeChange();
        await flushPromises();

        expect(wrapper.vm.listMode).toBe('list');
        expect(wrapper.vm.limit).toBe(10);
        expect(wrapper.vm.themeRepository.search).toHaveBeenCalled();
    });

    it('opens media modal when ACL allows', async () => {
        const wrapper = await createWrapper();
        const theme = { id: 'theme-id' };

        wrapper.vm.onPreviewChange(theme);

        expect(wrapper.vm.showMediaModal).toBe(true);
        expect(wrapper.vm.currentTheme).toStrictEqual(theme);
    });

    it('does not open media modal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });

        wrapper.vm.onPreviewChange({ id: 'theme-id' });

        expect(wrapper.vm.showMediaModal).toBe(false);
        expect(wrapper.vm.currentTheme).toBeUndefined();
    });

    it('removes preview image when ACL allows', async () => {
        const wrapper = await createWrapper();
        const theme = { previewMediaId: 'media-id', previewMedia: { id: 'media-id' } };

        wrapper.vm.onPreviewImageRemove(theme);
        await flushPromises();

        expect(theme.previewMediaId).toBeNull();
        expect(theme.previewMedia).toBeNull();
        expect(wrapper.vm.themeRepository.save).toHaveBeenCalledWith(theme, Contena.Context.api);
    });

    it('skips preview image removal when ACL blocks', async () => {
        const wrapper = await createWrapper({ aclCan: false });
        const theme = { previewMediaId: 'media-id', previewMedia: { id: 'media-id' } };

        wrapper.vm.onPreviewImageRemove(theme);

        expect(theme.previewMediaId).toBe('media-id');
    });

    it('updates current theme preview image', async () => {
        const wrapper = await createWrapper();
        const image = { id: 'media-id' };

        wrapper.vm.currentTheme = { previewMediaId: null, previewMedia: null };
        wrapper.vm.onPreviewImageChange([image]);
        await flushPromises();

        expect(wrapper.vm.currentTheme.previewMediaId).toBe('media-id');
        expect(wrapper.vm.currentTheme.previewMedia).toStrictEqual(image);
        expect(wrapper.vm.themeRepository.save).toHaveBeenCalledWith(wrapper.vm.currentTheme, Contena.Context.api);
    });

    it('saves theme and clears loading state on error', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.themeRepository.save.mockRejectedValueOnce(new Error('fail'));

        await wrapper.vm.saveTheme({ id: 'theme-id' });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('returns delete tooltip disabled when theme has no channel assignments', async () => {
        const wrapper = await createWrapper();
        const tooltip = wrapper.vm.deleteDisabledToolTip({ channels: [] });

        expect(tooltip.disabled).toBe(true);
    });
});
