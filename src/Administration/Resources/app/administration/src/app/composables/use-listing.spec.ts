import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, ref, type ComponentPublicInstance } from 'vue';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { useListing } from './use-listing';

interface ListingPublicInstance extends ComponentPublicInstance {
    page: number;
    limit: number;
    sortBy: string | null;
    sortDirection: string;
    naturalSorting: boolean;
    term: string | undefined;
    currentSortBy: string | null;
    onPageChange: (options: { page: number; limit: number }) => void;
    onSearch: (value: string) => void;
    onSortColumn: (column: { dataIndex: string; naturalSorting: boolean }) => void;
}

describe('src/app/composables/use-listing', () => {
    let wrapper: VueWrapper;
    let router: Router;
    let listing: VueWrapper<ListingPublicInstance>;
    let getList: jest.Mock;

    async function createWrapper({
        disableRouteParams = true,
        query = {},
    }: {
        disableRouteParams?: boolean;
        query?: Record<string, string>;
    } = {}): Promise<void> {
        getList = jest.fn();
        const ListingComponent = defineComponent({
            name: 'TestListing',
            setup() {
                const localDisableRouteParams = ref(disableRouteParams);
                const localSortBy = ref<string | null>('name');
                const state = useListing();

                state.initializeListing({
                    getList,
                    disableRouteParams: localDisableRouteParams,
                    sortBy: localSortBy,
                });

                return state;
            },
            template: '<div class="test-listing" />',
        });

        router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    name: 'listing',
                    path: '/listing',
                    component: ListingComponent,
                },
            ],
        });
        await router.push({ name: 'listing', query });

        wrapper = mount(defineComponent({ template: '<router-view />' }), {
            global: {
                plugins: [router],
                provide: {
                    searchRankingService: {
                        getSearchFieldsByEntity: jest.fn(() => ({})),
                        isValidTerm: jest.fn((term: string) => term.trim().length > 0),
                        buildSearchQueriesForEntity: jest.fn(),
                    },
                },
            },
        });
        await flushPromises();

        listing = wrapper.findComponent(ListingComponent) as VueWrapper<ListingPublicInstance>;
    }

    afterEach(() => {
        wrapper?.unmount();
    });

    it('uses component-owned state and loads the listing during initialization', async () => {
        await createWrapper();

        expect(listing.vm.sortBy).toBe('name');
        expect(listing.vm.page).toBe(1);
        expect(listing.vm.limit).toBe(25);
        expect(getList).toHaveBeenCalledTimes(1);
    });

    it('updates local pagination and reloads when route parameters are disabled', async () => {
        await createWrapper();
        getList.mockClear();

        listing.vm.onPageChange({ page: 3, limit: 50 });

        expect(listing.vm.page).toBe(3);
        expect(listing.vm.limit).toBe(50);
        expect(getList).toHaveBeenCalledTimes(1);
    });

    it('hydrates state from route query parameters before loading', async () => {
        await createWrapper({
            disableRouteParams: false,
            query: {
                page: '4',
                limit: '100',
                naturalSorting: 'true',
            },
        });

        expect(listing.vm.page).toBe(4);
        expect(listing.vm.limit).toBe(100);
        expect(listing.vm.naturalSorting).toBe(true);
        expect(getList).toHaveBeenCalledTimes(1);
    });

    it('resets pagination and tracks a fresh local search', async () => {
        await createWrapper();
        listing.vm.onPageChange({ page: 3, limit: 25 });
        getList.mockClear();

        listing.vm.onSearch('administrator');
        await flushPromises();

        expect(listing.vm.page).toBe(1);
        expect(listing.vm.term).toBe('administrator');
        expect(listing.vm.currentSortBy).toBeNull();
        expect(getList).toHaveBeenCalledTimes(1);
    });

    it('toggles the current sort direction for local listings', async () => {
        await createWrapper();
        getList.mockClear();

        listing.vm.onSortColumn({ dataIndex: 'name', naturalSorting: true });

        expect(listing.vm.sortBy).toBe('name');
        expect(listing.vm.sortDirection).toBe('DESC');
        expect(getList).toHaveBeenCalledTimes(1);
    });
});
