import { flushPromises, mount } from '@vue/test-utils';
import component from './ct-settings-search-searchable-content-customfields.vue';

type Vm = {
    customFieldFilteredCriteria: { filters: Array<{ type: string; field?: string; value?: unknown }> };
    customFields: Entity<'custom_field'>[];
    getMatchingCustomFields: (field: string) => string;
    onSelectCustomField: (field: Entity<'custom_field'>) => void;
    onResetRanking: (field: Entity<'blog_search_config_field'>) => void;
    onRemove: (field: Entity<'blog_search_config_field'>) => void;
};

const customField = {
    id: 'custom',
    name: 'author',
    config: { label: { 'en-GB': 'Author' } },
    customFieldSet: { id: 'set', name: 'content', config: { label: { 'en-GB': 'Content' } } },
} as unknown as Entity<'custom_field'>;
const config = {
    id: 'config',
    field: 'customFields.author',
    customFieldId: 'custom',
    ranking: 20,
} as Entity<'blog_search_config_field'>;
function createWrapper(
    isEmpty = false,
    privileges = [
        'blog_search_config.editor',
        'blog_search_config.deleter',
    ],
) {
    const search = jest.fn(() => Promise.resolve(Object.assign([customField], { total: 1 })));
    const wrapper = mount(component, {
        props: {
            isEmpty,
            columns: [{ property: 'field', label: 'Field', renderer: 'text', position: 100 }],
            searchConfigs: [config],
        },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-empty-state': { template: '<div class="mt-empty-state"><slot name="button" /></div>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-data-table': { props: ['dataSource'], template: '<div class="mt-data-table" />' },
            },
            provide: {
                repositoryFactory: { create: () => ({ search }) },
                acl: { can: (identifier: string) => privileges.includes(identifier) },
            },
        },
    });
    return wrapper as unknown as typeof wrapper & { vm: Vm };
}

describe('ct-settings-search-searchable-content-customfields', () => {
    it('renders the upstream empty state and add action', async () => {
        const wrapper = createWrapper(true, ['blog_search_config.creator']);
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('config-add')).toBeTruthy();
    });

    it('filters custom fields to includeInSearch and excludes already configured ids', async () => {
        const wrapper = createWrapper();
        await flushPromises();
        const vm = wrapper.vm as unknown as Vm;
        const filters = vm.customFieldFilteredCriteria.filters;
        expect(filters).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ field: 'includeInSearch', value: true }),
                expect.objectContaining({ type: 'not' }),
            ]),
        );
    });

    it('loads and displays custom fields with their set', async () => {
        const wrapper = createWrapper();
        await flushPromises();
        const vm = wrapper.vm as unknown as Vm;
        expect(vm.customFields).toHaveLength(1);
        expect(vm.getMatchingCustomFields('customFields.author')).toContain('Author');
    });

    it('selects, resets, and removes custom field configurations', async () => {
        const newConfig = Object.assign(config, { id: 'new', _isNew: true, field: '', customFieldId: undefined });
        const wrapper = createWrapper();
        await wrapper.setProps({ searchConfigs: [newConfig] });
        const vm = wrapper.vm as unknown as Vm;
        vm.onSelectCustomField(customField);
        expect(newConfig).toEqual(expect.objectContaining({ field: 'customFields.author', customFieldId: 'custom' }));
        vm.onResetRanking(newConfig);
        expect(newConfig.ranking).toBe(0);
        vm.onRemove(newConfig);
        expect(wrapper.emitted('config-save')).toBeTruthy();
        expect(wrapper.emitted('config-delete')).toContainEqual(['new']);
    });
});
