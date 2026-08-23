import { flushPromises, mount } from '@vue/test-utils';
import component from './ct-settings-search-search-index.vue';

type Vm = {
    isRebuildSuccess: boolean;
    isRebuildInProgress: boolean;
    progressBarValue: number;
    offset: number;
    latestIndex: { lastDate: string } | null;
    totalBlog: number;
    rebuildSearchIndex: () => void;
    updateProgress: () => Promise<void>;
    getLatestBlogKeywordIndexed: () => Promise<void>;
    clearPolling: () => void;
};

function createWrapper(
    privileges: string[] = [],
    responses: Array<{ finish: boolean; offset?: { offset: number } }> = [{ finish: true }],
) {
    const blogSearch = jest.fn(() => Promise.resolve(Object.assign([], { total: 3 })));
    const keywordSearch = jest.fn(() =>
        Promise.resolve(Object.assign([], { total: 1, aggregations: { lastDate: { max: '2026-08-13' } } })),
    );
    const index = jest.fn(() => Promise.resolve({ data: responses.shift() ?? { finish: true } }));
    const wrapper = mount(component, {
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<section><slot /></section>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-banner': true,
                'mt-progress-bar': true,
                'ct-time-ago': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => ({ search: entity === 'blog' ? blogSearch : keywordSearch }),
                },
                blogIndexService: { index },
                acl: { can: (identifier: string) => privileges.includes(identifier) },
            },
        },
    });
    return { wrapper, vm: wrapper.vm as unknown as Vm, index, keywordSearch };
}

describe('ct-settings-search-search-index', () => {
    it('disables rebuild without editor privilege', async () => {
        const { wrapper, vm } = createWrapper(['blog_search_config.viewer']);
        await flushPromises();
        expect(wrapper.get('.ct-settings-search__search-index-rebuild-button').attributes('disabled')).toBeDefined();
        vm.clearPolling();
    });

    it('loads Blog total and latest keyword build', async () => {
        const { vm } = createWrapper();
        await flushPromises();
        expect(vm.totalBlog).toBe(3);
        expect(vm.latestIndex).toEqual({ lastDate: '2026-08-13' });
        vm.clearPolling();
    });

    it('starts the upstream polling flow and emits editing state', async () => {
        jest.useFakeTimers();
        const { wrapper, vm } = createWrapper(['blog_search_config.editor']);
        await flushPromises();
        vm.rebuildSearchIndex();
        expect(vm.isRebuildInProgress).toBe(true);
        expect(vm.progressBarValue).toBe(1);
        expect(wrapper.emitted('edit-change')).toContainEqual([true]);
        vm.clearPolling();
        jest.useRealTimers();
    });

    it('iterates offsets until the Blog index is finished', async () => {
        const { wrapper, vm, index } = createWrapper(
            ['blog_search_config.editor'],
            [
                { finish: false, offset: { offset: 2 } },
                { finish: true },
            ],
        );
        await flushPromises();
        vm.isRebuildInProgress = true;
        await vm.updateProgress();
        expect(index).toHaveBeenNthCalledWith(1, 0);
        expect(index).toHaveBeenNthCalledWith(2, 2);
        expect(vm.isRebuildInProgress).toBe(false);
        expect(vm.progressBarValue).toBe(0);
        expect(wrapper.emitted('edit-change')).toContainEqual([false]);
    });

    it('keeps latestIndex empty when no keyword has been indexed', async () => {
        const { vm, keywordSearch } = createWrapper();
        await flushPromises();
        keywordSearch.mockResolvedValueOnce(Object.assign([], { total: 0, aggregations: { lastDate: { max: '' } } }));
        vm.latestIndex = null;
        await vm.getLatestBlogKeywordIndexed();
        expect(vm.latestIndex).toBeNull();
        vm.clearPolling();
    });
});
