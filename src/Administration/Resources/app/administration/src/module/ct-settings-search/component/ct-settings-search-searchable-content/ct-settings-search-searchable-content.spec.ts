import { flushPromises, mount } from '@vue/test-utils';
import component from './ct-settings-search-searchable-content.vue';

type Vm = {
    fieldConfigs: Array<{ value: string }>;
    defaultTab: string;
    searchConfigFields: Entity<'blog_search_config_field'>[];
    getConfigFieldDefault: (field: string) => { searchable: boolean; ranking: number; tokenize: boolean };
    onShowExampleModal: () => void;
    onChangeTab: (tab: string) => void;
    onAddNewConfig: () => void;
    saveConfig: () => Promise<void>;
    deleteConfig: (id: string) => Promise<void>;
    $nextTick: () => Promise<void>;
};

function createWrapper(privileges: string[] = []) {
    const search = jest.fn(() => Promise.resolve(Object.assign([], { total: 0 })));
    const saveAll = jest.fn(() => Promise.resolve());
    const remove = jest.fn(() => Promise.resolve());
    const create = jest.fn(() => ({ id: 'new', _isNew: true, isNew: () => true }));
    const repository = { search, saveAll, delete: remove, create };
    const wrapper = mount(component, {
        props: { searchConfigId: 'config' },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<section><slot /></section>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-link': { template: '<a><slot /></a>' },
                'mt-tabs': {
                    props: [
                        'defaultItem',
                        'items',
                    ],
                    emits: ['new-item-active'],
                    template: '<div class="mt-tabs" />',
                },
                'ct-settings-search-example-modal': { template: '<div class="ct-settings-search-example-modal" />' },
                'ct-settings-search-searchable-content-general': { template: '<div class="general" />' },
                'ct-settings-search-searchable-content-customfields': { template: '<div class="custom" />' },
            },
            provide: {
                repositoryFactory: { create: () => repository },
                acl: { can: (identifier: string) => privileges.includes(identifier) },
            },
        },
    });
    return { wrapper: wrapper as unknown as typeof wrapper & { vm: Vm }, repository };
}

describe('ct-settings-search-searchable-content', () => {
    it('uses only mapped Blog search fields and their defaults', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        const vm = wrapper.vm as unknown as Vm;
        expect(vm.fieldConfigs.map(({ value }) => value)).toEqual([
            'name',
            'description',
            'descriptionTeaser',
            'keywords',
            'customSearchKeywords',
            'categories.name',
            'categories.customFields',
            'tags.name',
            'metaTitle',
            'metaDescription',
        ]);
        expect(vm.getConfigFieldDefault('name')).toEqual({ searchable: true, ranking: 500, tokenize: true });
        expect(vm.getConfigFieldDefault('unknown')).toEqual({ searchable: false, ranking: 0, tokenize: false });
    });

    it('shows the example modal and switches Meteor tabs', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        const vm = wrapper.vm as unknown as Vm;
        vm.onShowExampleModal();
        await vm.$nextTick();
        expect(wrapper.find('.ct-settings-search-example-modal').exists()).toBe(true);
        vm.onChangeTab('customfields');
        await flushPromises();
        expect(vm.defaultTab).toBe('customfields');
        expect(wrapper.find('.custom').exists()).toBe(true);
    });

    it('creates a Blog search config field with upstream defaults', async () => {
        const { wrapper, repository } = createWrapper(['blog_search_config.creator']);
        await flushPromises();
        const vm = wrapper.vm as unknown as Vm;
        vm.onAddNewConfig();
        expect(repository.create).toHaveBeenCalled();
        expect(vm.searchConfigFields[0]).toEqual(
            expect.objectContaining({
                searchConfigId: 'config',
                searchable: false,
                ranking: 0,
                field: '',
                tokenize: false,
            }),
        );
        expect(wrapper.emitted('edit-change')).toContainEqual([true]);
    });

    it('saves and deletes through the blog_search_config_field repository', async () => {
        const { wrapper, repository } = createWrapper(['blog_search_config.editor']);
        await flushPromises();
        const vm = wrapper.vm as unknown as Vm;
        await vm.saveConfig();
        await vm.deleteConfig('field');
        expect(repository.saveAll).toHaveBeenCalled();
        expect(repository.delete).toHaveBeenCalledWith('field', Contena.Context.api);
        expect(wrapper.emitted('edit-change')).toContainEqual([false]);
    });
});
