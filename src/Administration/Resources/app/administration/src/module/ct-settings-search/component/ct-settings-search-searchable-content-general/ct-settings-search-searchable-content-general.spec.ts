import { mount } from '@vue/test-utils';
import component from './ct-settings-search-searchable-content-general.vue';

type Vm = {
    getMatchingFields: (field: string) => string;
    onSelectField: (field: Entity<'blog_search_config_field'>) => void;
    onResetRanking: (field: Entity<'blog_search_config_field'>) => void;
};

const config = {
    id: 'field',
    field: 'name',
    ranking: 1,
    searchable: true,
    tokenize: true,
} as Entity<'blog_search_config_field'>;
function createWrapper(isEmpty = false) {
    const wrapper = mount(component, {
        props: {
            isEmpty,
            columns: [{ property: 'field', label: 'Field', renderer: 'text', position: 100 }],
            searchConfigs: [config],
            fieldConfigs: [
                {
                    value: 'name',
                    label: 'Article title',
                    defaultConfigs: { ranking: 500, searchable: true, tokenize: true },
                },
            ],
        },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-empty-state': { template: '<div class="mt-empty-state" />' },
                'mt-data-table': { props: ['dataSource'], template: '<div class="mt-data-table" />' },
            },
            provide: { acl: { can: () => true } },
        },
    });
    return wrapper as unknown as typeof wrapper & { vm: Vm };
}

describe('ct-settings-search-searchable-content-general', () => {
    it('renders the upstream empty state', () => {
        expect(createWrapper(true).find('.mt-empty-state').exists()).toBe(true);
    });

    it('resolves field labels and applies the mapped field defaults', () => {
        const wrapper = createWrapper();
        const field = Object.assign(config, { ranking: 0 });
        const vm = wrapper.vm as unknown as Vm;
        expect(vm.getMatchingFields('name')).toBe('Article title');
        vm.onSelectField(field);
        expect(field.ranking).toBe(500);
        expect(wrapper.emitted('config-save')).toBeTruthy();
    });

    it('resets the ranking and emits the upstream save event', () => {
        const wrapper = createWrapper();
        const vm = wrapper.vm as unknown as Vm;
        vm.onResetRanking(config);
        expect(config.ranking).toBe(500);
        expect(wrapper.emitted('config-save')).toBeTruthy();
    });
});
