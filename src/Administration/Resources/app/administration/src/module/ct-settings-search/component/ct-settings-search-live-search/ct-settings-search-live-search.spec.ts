import { defineComponent } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import component from './ct-settings-search-live-search.vue';

type SearchItem = Entity<'blog'> & {
    extensions?: { search?: { _score?: number; matched_queries?: Record<string, number> } };
};
type LiveSearchVm = {
    channels: Entity<'channel'>[];
    blogSortings: Entity<'blog_sorting'>[];
    channelId: string | null;
    liveSearchTerm: string;
    executedSearchTerm: string;
    sortingKey: string | null;
    liveSearchResults: { elements?: SearchItem[] } | null;
    resultItems: SearchItem[];
    selectedExplainId: string | null;
    selectedExplainItem: SearchItem | null;
    scoresAreUniform: boolean;
    currentSearchTerm: string;
    resultOffset: number;
    searchOnChannel: (term?: string) => void;
    changeChannel: (id: string | null) => void;
    hasExplain: (item: SearchItem) => boolean;
    toggleExplain: (item: SearchItem) => void;
    isExplainOpen: (item: SearchItem) => boolean;
    getScoreValue: (item: SearchItem) => number;
    formatScore: (value: string | number) => string;
    getRank: (item: SearchItem) => number | null;
    getScoreBarWidth: (item: SearchItem) => string;
};

const channels = [{ id: 'web', name: 'Web', translated: { name: 'Web' } }] as Entity<'channel'>[];
const sortings = [
    { id: 'score', key: 'score', priority: 10, active: true, label: 'Top', translated: { label: 'Top' } },
    { id: 'name', key: 'name-asc', priority: 2, active: true, label: 'Name', translated: { label: 'Name' } },
] as Entity<'blog_sorting'>[];
const results: SearchItem[] = [
    { id: 'one', name: 'First article', extensions: { search: { _score: 40320 } } } as SearchItem,
    { id: 'two', name: 'Second article', extensions: { search: { _score: 34560 } } } as SearchItem,
    { id: 'three', name: 'Third article', extensions: { search: { _score: 34559.9999 } } } as SearchItem,
];
const selectStub = defineComponent({
    props: [
        'modelValue',
        'options',
        'disabled',
    ],
    emits: ['update:modelValue'],
    template: '<div class="mt-select" />',
});
const searchStub = defineComponent({
    props: [
        'modelValue',
        'disabled',
    ],
    emits: [
        'update:modelValue',
        'change',
    ],
    template: '<input class="mt-search__input" :disabled="disabled" />',
});

function createWrapper(search = jest.fn(() => Promise.resolve({ data: { elements: results } }))) {
    const channelSearch = jest.fn(() => Promise.resolve(Object.assign([...channels], { total: channels.length })));
    const sortingSearch = jest.fn(() => Promise.resolve(Object.assign([...sortings], { total: sortings.length })));
    const wrapper = mount(component, {
        props: { currentChannelId: null, searchTerms: '', searchResults: null },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<section><slot /></section>' },
                'mt-link': { template: '<button><slot /></button>' },
                'mt-select': selectStub,
                'mt-search': searchStub,
                'mt-data-table': {
                    props: ['dataSource'],
                    template:
                        '<div class="mt-data-table"><slot v-for="item in dataSource" name="column-score" :data="item" /></div>',
                },
                'mt-empty-state': { template: '<div class="mt-empty-state" />' },
                'mt-loader': true,
                'mt-icon': true,
                'ct-settings-search-live-search-keyword': true,
                'ct-settings-search-live-search-explain': true,
                'ct-settings-search-example-modal': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => ({ search: entity === 'channel' ? channelSearch : sortingSearch }),
                },
                liveSearchService: { search },
            },
        },
    }) as unknown as VueWrapper<LiveSearchVm>;
    return { wrapper, search, channelSearch, sortingSearch };
}

describe('ct-settings-search-live-search', () => {
    it('loads Channels and active Blog sortings with score as default', async () => {
        const { wrapper, channelSearch, sortingSearch } = createWrapper();
        await flushPromises();

        expect(channelSearch).toHaveBeenCalledTimes(1);
        expect(sortingSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.channels).toHaveLength(1);
        expect(wrapper.vm.blogSortings).toHaveLength(2);
        expect(wrapper.vm.sortingKey).toBe('score');
    });

    it('keeps search disabled until a Channel is selected', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        expect(wrapper.find('.mt-search__input').attributes('disabled')).toBeDefined();

        wrapper.vm.changeChannel('web');
        await flushPromises();
        expect(wrapper.find('.mt-search__input').attributes('disabled')).toBeUndefined();
        expect(wrapper.emitted('channel-change')?.[0]).toEqual(['web']);
    });

    it('searches through the Channel API with the selected sorting', async () => {
        const { wrapper, search } = createWrapper();
        await flushPromises();
        wrapper.vm.changeChannel('web');
        wrapper.vm.sortingKey = 'name-asc';
        wrapper.vm.searchOnChannel('article');
        await flushPromises();

        expect(search).toHaveBeenCalledWith(
            { channelId: 'web', search: 'article', order: 'name-asc' },
            '',
            {},
            expect.objectContaining({ 'ct-language-id': Contena.Context.api.languageId }),
        );
        expect(wrapper.vm.executedSearchTerm).toBe('article');
        expect(wrapper.vm.resultItems).toHaveLength(3);
    });

    it('does not search without a Channel or without a search term', async () => {
        const { wrapper, search } = createWrapper();
        await flushPromises();
        wrapper.vm.searchOnChannel('article');
        wrapper.vm.changeChannel('web');
        wrapper.vm.searchOnChannel('');
        expect(search).not.toHaveBeenCalled();
    });

    it('keeps the previously executed term when a search fails', async () => {
        const search = jest.fn(() => Promise.reject(new Error('boom')));
        const { wrapper } = createWrapper(search);
        await flushPromises();
        wrapper.vm.changeChannel('web');
        wrapper.vm.executedSearchTerm = 'article';
        wrapper.vm.searchOnChannel('guide');
        await flushPromises();

        expect(wrapper.vm.currentSearchTerm).toBe('article');
    });

    it('formats score values without rounding a non-integer to an integer', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        expect(wrapper.vm.formatScore(28799.999999)).toBe('28800.0');
        expect(wrapper.vm.formatScore(40320)).toBe('40320');
        expect(wrapper.vm.getScoreBarWidth(results[0])).toBe('0%');
    });

    it('calculates rank and relative score bars for results', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        wrapper.vm.liveSearchResults = { elements: results };
        await flushPromises();

        expect(wrapper.vm.getRank(results[0])).toBe(1);
        expect(wrapper.vm.getRank(results[2])).toBe(3);
        expect(wrapper.vm.getScoreBarWidth(results[0])).toBe('100%');
        expect(Number.parseFloat(wrapper.vm.getScoreBarWidth(results[1]))).toBeGreaterThan(80);
    });

    it('marks results explainable only when a field clause matched', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        const field = {
            id: 'field',
            extensions: { search: { matched_queries: { [JSON.stringify({ field: 'name', term: 'article' })]: 10 } } },
        } as SearchItem;
        const extension = {
            id: 'extension',
            extensions: { search: { matched_queries: { [JSON.stringify({ boost: 5, name: 'rule' })]: 10 } } },
        } as SearchItem;

        expect(wrapper.vm.hasExplain(field)).toBe(true);
        expect(wrapper.vm.hasExplain(extension)).toBe(false);
        expect(wrapper.vm.hasExplain({ id: 'plain' } as SearchItem)).toBe(false);
    });

    it('opens, closes, and resets the selected explain row', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        const item = {
            id: 'article',
            extensions: { search: { matched_queries: { [JSON.stringify({ field: 'name', term: 'article' })]: 10 } } },
        } as SearchItem;
        wrapper.vm.liveSearchResults = { elements: [item] };
        await flushPromises();

        wrapper.vm.toggleExplain(item);
        expect(wrapper.vm.isExplainOpen(item)).toBe(true);
        expect(wrapper.vm.selectedExplainItem).toStrictEqual(item);
        wrapper.vm.toggleExplain(item);
        expect(wrapper.vm.selectedExplainId).toBeNull();

        wrapper.vm.selectedExplainId = item.id;
        wrapper.vm.searchOnChannel('');
        expect(wrapper.vm.selectedExplainId).toBeNull();
    });

    it('detects uniform scores only for result sets with at least two rows', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();
        wrapper.vm.liveSearchResults = { elements: [{ id: 'one', extensions: { search: { _score: 10 } } } as SearchItem] };
        await flushPromises();
        expect(wrapper.vm.scoresAreUniform).toBe(false);

        wrapper.vm.liveSearchResults = {
            elements: [
                { id: 'one', extensions: { search: { _score: 10 } } } as SearchItem,
                { id: 'two', extensions: { search: { _score: 10 } } } as SearchItem,
            ],
        };
        await flushPromises();
        expect(wrapper.vm.scoresAreUniform).toBe(true);
    });
});
