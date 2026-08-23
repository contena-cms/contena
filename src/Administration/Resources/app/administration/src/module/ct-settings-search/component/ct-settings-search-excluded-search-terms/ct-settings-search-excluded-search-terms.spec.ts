import { flushPromises, mount } from '@vue/test-utils';
import component from './ct-settings-search-excluded-search-terms.vue';

type Vm = {
    items: Array<{ id: string; value: string; originalValue: string }>;
    originalItems: string[];
    showEmptyState: boolean;
    page: number;
    limit: number;
    total: number;
    searchTerm: string;
    isAddingItem: boolean;
    onInsertTerm: () => void;
    onSearchTermChange: (term: string) => void;
    onPageChange: (page: number) => void;
    onLimitChange: (limit: number) => void;
    onSaveEdit: (term: { id: string; value: string; originalValue: string }) => void;
    onDeleteExcludedTerm: (terms: Array<{ id: string; value: string; originalValue: string }>) => void;
    onResetExcludedSearchTermDefault: () => Promise<void>;
};

const terms = [
    'i',
    'a',
    'on',
    'in',
    'of',
    'at',
    'right',
    'he',
    'she',
    'we',
    'us',
    'our',
];
function createWrapper(privileges: string[] = [], resetError = false) {
    const save = jest.fn(() => Promise.resolve());
    const resetExcludedSearchTerm = jest.fn(() => (resetError ? Promise.reject(new Error('reset')) : Promise.resolve()));
    const wrapper = mount(component, {
        props: { searchConfigs: { id: 'config', excludedTerms: [...terms] } as Entity<'blog_search_config'> },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<section><slot /></section>' },
                'mt-search': true,
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-data-table': { props: ['dataSource'], template: '<div class="mt-data-table" />' },
                'mt-empty-state': { template: '<div class="mt-empty-state"><slot name="button" /></div>' },
            },
            provide: {
                repositoryFactory: { create: () => ({ save }) },
                excludedSearchTermService: { resetExcludedSearchTerm },
                acl: { can: (identifier: string) => privileges.includes(identifier) },
            },
        },
    });
    return { wrapper, vm: wrapper.vm as unknown as Vm, save, resetExcludedSearchTerm };
}

describe('ct-settings-search-excluded-search-terms', () => {
    it('renders and paginates the complete excluded term list', async () => {
        const { vm } = createWrapper();
        await flushPromises();
        expect(vm.total).toBe(12);
        expect(vm.items).toHaveLength(10);
        vm.onPageChange(2);
        expect(vm.items).toHaveLength(2);
        vm.onLimitChange(25);
        expect(vm.items).toHaveLength(12);
    });

    it('filters terms and renders no-result state without losing source terms', async () => {
        const { wrapper, vm } = createWrapper();
        await flushPromises();
        vm.onSearchTermChange('right');
        expect(vm.items.map(({ value }) => value)).toEqual(['right']);
        vm.onSearchTermChange('missing');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
        expect(vm.originalItems).toHaveLength(12);
    });

    it('adds, updates, rejects duplicates, and deletes terms', async () => {
        const { vm, save } = createWrapper([
            'blog_search_config.creator',
            'blog_search_config.editor',
            'blog_search_config.deleter',
        ]);
        await flushPromises();
        vm.onInsertTerm();
        expect(vm.isAddingItem).toBe(true);
        vm.onSaveEdit({ id: 'new', value: 'guide', originalValue: '' });
        await flushPromises();
        expect(vm.originalItems).toContain('guide');
        vm.onSaveEdit({ id: 'duplicate', value: 'guide', originalValue: '' });
        await flushPromises();
        expect(vm.originalItems.filter((term) => term === 'guide')).toHaveLength(1);
        vm.onDeleteExcludedTerm([{ id: 'guide', value: 'guide', originalValue: 'guide' }]);
        await flushPromises();
        expect(vm.originalItems).not.toContain('guide');
        expect(save).toHaveBeenCalled();
    });

    it('disables create actions without creator privilege', async () => {
        const { wrapper } = createWrapper(['blog_search_config.viewer']);
        await flushPromises();
        expect(wrapper.get('.ct-settings-search-excluded-search-terms__insert-button').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.ct-settings-search-excluded-search-terms__reset-button').attributes('disabled')).toBeDefined();
    });

    it('resets upstream stop words and requests a reload', async () => {
        const { wrapper, vm, resetExcludedSearchTerm } = createWrapper(['blog_search_config.creator']);
        await vm.onResetExcludedSearchTermDefault();
        expect(resetExcludedSearchTerm).toHaveBeenCalled();
        expect(wrapper.emitted('data-load')).toBeTruthy();
    });

    it('does not request a reload when resetting fails', async () => {
        const { wrapper, vm } = createWrapper(['blog_search_config.creator'], true);
        await vm.onResetExcludedSearchTermDefault();
        expect(wrapper.emitted('data-load')).toBeFalsy();
    });
});
