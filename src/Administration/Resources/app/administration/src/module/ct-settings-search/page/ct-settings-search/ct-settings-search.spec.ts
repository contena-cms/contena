import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './ct-settings-search.vue';

type Vm = {
    blogSearchConfigs: Entity<'blog_search_config'> | null;
    defaultConfig: Entity<'blog_search_config'> | null;
    currentChannelId: string | null;
    searchTerms: string;
    searchResults: unknown;
    isEditing: boolean;
    isDisplayingLeavePageWarning: boolean;
    allowSave: boolean;
    settingsSearchTabs: Array<{ name: string; onClick: () => void }>;
    getBlogSearchConfigs: () => Promise<void>;
    getDefaultSearchConfig: () => Promise<void>;
    createDefaultSearchConfig: () => Entity<'blog_search_config'> | null;
    onSaveSearchSettings: () => Promise<void>;
    unsavedDataLeaveHandler: (to: never) => boolean;
    onChannelChanged: (id: string | null) => void;
    onLiveSearchResultsChanged: (payload: { searchTerms: string; searchResults: unknown }) => void;
    onEditChanged: (editing: boolean) => void;
};

const languageConfig = {
    id: 'config',
    andLogic: false,
    minSearchLength: 4,
    excludedTerms: [],
    languageId: Contena.Context.api.languageId,
    configFields: [],
} as unknown as Entity<'blog_search_config'>;
const systemConfig = {
    id: 'default',
    andLogic: true,
    minSearchLength: 2,
    excludedTerms: [],
    languageId: Contena.Context.api.systemLanguageId,
    configFields: [],
} as unknown as Entity<'blog_search_config'>;

const originalLanguageId = Contena.Context.api.languageId;
const originalSystemLanguageId = Contena.Context.api.systemLanguageId;

async function createWrapper(privileges: string[] = [], saveError = false) {
    const search = jest.fn((criteria: { filters?: Array<{ value?: string }> }) => {
        const languageId = criteria.filters?.[0]?.value;
        const item = languageId === Contena.Context.api.systemLanguageId ? systemConfig : languageConfig;
        return Promise.resolve(Object.assign([item], { total: 1, first: () => item }));
    });
    const save = jest.fn((entity: unknown) =>
        entity && !saveError ? Promise.resolve() : Promise.reject(new Error('save')),
    );
    const create = jest.fn(() => ({ id: 'new', configFields: [] }));
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/search/general', name: 'ct.settings.search.index.general', component },
            { path: '/search/live-search', name: 'ct.settings.search.index.liveSearch', component: { template: '<div />' } },
        ],
    });
    await router.push('/search/general');
    await router.isReady();
    const routerPush = jest.spyOn(router, 'push');
    Object.assign(Contena, { ExtensionAPI: { publishData: jest.fn() } });
    const root = mount(defineComponent({ template: '<router-view />' }), {
        global: {
            plugins: [router],
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'ct-page': {
                    template:
                        '<main><slot name="smart-bar-header" /><slot name="language-switch" /><slot name="smart-bar-actions" /><slot name="content" /></main>',
                },
                'ct-language-switch': true,
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-icon': true,
                'ct-card-view': { template: '<div><slot /></div>' },
                'mt-tabs': defineComponent({
                    name: 'MtTabs',
                    props: [
                        'defaultItem',
                        'items',
                    ],
                    template: '<div class="mt-tabs" />',
                }),
                'ct-skeleton': true,
                'mt-modal-root': true,
                'mt-modal': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({ search, save, create, route: '/search', entityName: 'blog_search_config_field' }),
                },
                acl: { can: (identifier: string) => privileges.includes(identifier) },
            },
        },
    });
    await flushPromises();
    const wrapper = root.findComponent(component);
    return { root, wrapper, vm: wrapper.vm as unknown as Vm, search, save, routerPush };
}

describe('ct-settings-search', () => {
    beforeEach(() => {
        Contena.Context.api.languageId = 'current-language';
        Contena.Context.api.systemLanguageId = 'system-language';
    });

    afterEach(() => {
        Contena.Context.api.languageId = originalLanguageId;
        Contena.Context.api.systemLanguageId = originalSystemLanguageId;
    });

    it('renders final Meteor tabs and navigates through both routes', async () => {
        const { wrapper, vm, routerPush } = await createWrapper();
        await flushPromises();
        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('items')).toHaveLength(2);
        vm.settingsSearchTabs[0].onClick();
        vm.settingsSearchTabs[1].onClick();
        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.settings.search.index.general' });
        expect(routerPush).toHaveBeenCalledWith({ name: 'ct.settings.search.index.liveSearch' });
    });

    it('allows saving only for editor or creator roles', async () => {
        const viewer = await createWrapper(['blog_search_config.viewer']);
        const editor = await createWrapper(['blog_search_config.editor']);
        await flushPromises();
        expect(viewer.vm.allowSave).toBe(false);
        expect(viewer.wrapper.get('.ct-settings-search__button-save').attributes('disabled')).toBeDefined();
        expect(editor.vm.allowSave).toBe(true);
    });

    it('loads current and system-language Blog search configurations', async () => {
        const { vm, search } = await createWrapper();
        await flushPromises();
        expect(vm.blogSearchConfigs?.id).toBe('config');
        expect(vm.defaultConfig?.id).toBe('default');
        expect(search).toHaveBeenCalled();
    });

    it('copies upstream defaults for a missing language configuration', async () => {
        const { vm } = await createWrapper();
        await flushPromises();
        vm.defaultConfig = systemConfig;
        const created = vm.createDefaultSearchConfig();
        expect(created).toEqual(
            expect.objectContaining({
                andLogic: true,
                minSearchLength: 2,
                excludedTerms: [],
                languageId: Contena.Context.api.languageId,
            }),
        );
    });

    it('saves the current Blog search configuration', async () => {
        const { vm, save } = await createWrapper(['blog_search_config.editor']);
        await flushPromises();
        await vm.onSaveSearchSettings();
        expect(save).toHaveBeenCalledWith(languageConfig, Contena.Context.api);
    });

    it('does not save a missing or rejected configuration', async () => {
        const missing = await createWrapper(['blog_search_config.editor']);
        await flushPromises();
        missing.vm.blogSearchConfigs = null;
        await missing.vm.onSaveSearchSettings();
        expect(missing.save).not.toHaveBeenCalled();

        const rejected = await createWrapper(['blog_search_config.editor'], true);
        await flushPromises();
        await rejected.vm.onSaveSearchSettings();
        expect(rejected.save).toHaveBeenCalled();
    });

    it('blocks route changes while edits are unsaved', async () => {
        const { vm } = await createWrapper();
        await flushPromises();
        vm.onEditChanged(true);
        expect(vm.unsavedDataLeaveHandler({ name: 'target' } as never)).toBe(false);
        expect(vm.isDisplayingLeavePageWarning).toBe(true);
    });

    it('keeps Channel and live-search state while switching tabs', async () => {
        const { vm } = await createWrapper();
        await flushPromises();
        const results = { elements: [{ id: 'blog' }] };
        vm.onChannelChanged('web');
        vm.onLiveSearchResultsChanged({ searchTerms: 'article', searchResults: results });
        expect(vm.currentChannelId).toBe('web');
        expect(vm.searchTerms).toBe('article');
        expect(vm.searchResults).toStrictEqual(results);
    });
});
