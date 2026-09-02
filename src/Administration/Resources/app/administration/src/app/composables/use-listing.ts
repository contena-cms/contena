import type Criteria from '@contena/meteor-admin-sdk/es/data/Criteria';
import { computed, inject, ref, watch, type Ref } from 'vue';
import {
    matchedRouteKey,
    onBeforeRouteLeave,
    useRoute,
    useRouter,
    type LocationQuery,
    type RouteLocationNamedRaw,
} from 'vue-router';

interface SearchRankingService {
    getSearchFieldsByEntity(entityName: string): Record<string, unknown>;
    isValidTerm(term: string): boolean;
    buildSearchQueriesForEntity(
        searchRankingFields: Record<string, unknown>,
        term: string,
        criteria: Criteria,
    ): Criteria | Promise<Criteria>;
}

interface ListingState {
    page: number;
    limit: number;
    total: number;
    sortBy: string | null;
    sortDirection: string;
    naturalSorting: boolean;
    selection: Record<string, unknown>;
    term: string | null | undefined;
    disableRouteParams: boolean;
    searchConfigEntity: string | null;
    entitySearchable: boolean;
    freshSearchTerm: boolean;
    previousRouteName: string;
}

type ListingStateRefs = {
    [Key in keyof ListingState]: Ref<ListingState[Key]>;
};

interface ListingFilter {
    active: boolean;
}

/** @private */
export type InitializeListingOptions = Partial<ListingStateRefs> & {
    getList?: () => unknown;
    filters?: Ref<ListingFilter[]>;
    filterCriteria?: Ref<unknown[]>;
    storeKey?: Ref<string | undefined>;
};

/**
 * Composition API equivalent of the legacy `listing` mixin.
 *
 * Component data and methods override mixin members in the Options API. The
 * initializer accepts those component-owned refs and callbacks so migrated
 * components preserve the same precedence without duplicating listing logic.
 *
 * @private
 */
export function useListing() {
    const route = useRoute();
    const router = useRouter();
    const matchedRoute = inject(matchedRouteKey, null);
    const searchRankingService = inject<SearchRankingService>('searchRankingService');
    const state: ListingStateRefs = {
        page: ref(1),
        limit: ref(25),
        total: ref(0),
        sortBy: ref(null),
        sortDirection: ref('ASC'),
        naturalSorting: ref(false),
        selection: ref([] as unknown as Record<string, unknown>),
        term: ref(undefined),
        disableRouteParams: ref(false),
        searchConfigEntity: ref(null),
        entitySearchable: ref(true),
        freshSearchTerm: ref(false),
        previousRouteName: ref(''),
    };
    let filtersSource: Ref<ListingFilter[]> = ref([]);
    let filterCriteriaSource: Ref<unknown[]> = ref([]);
    let storeKeySource: Ref<string | undefined> = ref(undefined);
    let initialized = false;
    let getListCallback = () => {
        Contena.Utils.debug.warn(
            'Listing Composable',
            'When using the listing composable you have to provide your custom "getList()" method.',
        );
    };

    const createStateProxy = <Key extends keyof ListingState>(key: Key) =>
        computed({
            get: () => state[key].value,
            set: (value: ListingState[Key]) => {
                state[key].value = value;
            },
        });

    const page = createStateProxy('page');
    const limit = createStateProxy('limit');
    const total = createStateProxy('total');
    const sortBy = createStateProxy('sortBy');
    const sortDirection = createStateProxy('sortDirection');
    const naturalSorting = createStateProxy('naturalSorting');
    const selection = createStateProxy('selection');
    const term = createStateProxy('term');
    const disableRouteParams = createStateProxy('disableRouteParams');
    const searchConfigEntity = createStateProxy('searchConfigEntity');
    const entitySearchable = createStateProxy('entitySearchable');
    const freshSearchTerm = createStateProxy('freshSearchTerm');
    const previousRouteName = createStateProxy('previousRouteName');

    const maxPage = computed(() => Math.ceil(total.value / limit.value));
    const routeName = computed(() => route.name);
    const selectionArray = computed(() => Object.values(selection.value));
    const selectionCount = computed(() => selectionArray.value.length);
    const filters = computed(() => filtersSource.value);
    const searchRankingFields = computed(() => {
        if (!searchConfigEntity.value) {
            return {};
        }

        return searchRankingService?.getSearchFieldsByEntity(searchConfigEntity.value) ?? {};
    });
    const currentSortBy = computed(() => (freshSearchTerm.value ? null : sortBy.value));

    function getList(): unknown {
        return getListCallback();
    }

    function updateData(customData: {
        page?: number;
        limit?: number;
        term?: string | null;
        sortBy?: string;
        sortDirection?: string;
        naturalSorting?: boolean;
    }): void {
        page.value = Number.parseInt(customData.page as unknown as string, 10) || page.value;
        limit.value = Number.parseInt(customData.limit as unknown as string, 10) || limit.value;
        term.value = customData.term ?? term.value;
        sortBy.value = customData.sortBy || sortBy.value;
        sortDirection.value = customData.sortDirection || sortDirection.value;
        naturalSorting.value = customData.naturalSorting || naturalSorting.value;
    }

    function updateRoute(
        customQuery: {
            limit?: number;
            page?: number;
            term?: string | null;
            sortBy?: string;
            sortDirection?: string;
            naturalSorting?: boolean;
        } = {},
        queryExtension = {},
    ): void {
        const query = customQuery || route.query;
        const targetRoute = {
            name: route.name,
            params: route.params,
            query: {
                limit: query.limit || limit.value,
                page: query.page || page.value,
                term: query.term || term.value,
                sortBy: query.sortBy || sortBy.value,
                sortDirection: query.sortDirection || sortDirection.value,
                naturalSorting: query.naturalSorting || naturalSorting.value,
                ...queryExtension,
            },
        };

        if (Contena.Utils.types.isEmpty(route.query)) {
            void router.replace(targetRoute as unknown as RouteLocationNamedRaw);
        } else {
            void router.push(targetRoute as unknown as RouteLocationNamedRaw);
        }
    }

    function resetListing(): void {
        updateRoute({
            limit: limit.value,
            page: page.value,
            term: term.value,
            sortBy: sortBy.value ?? undefined,
            sortDirection: sortDirection.value,
            naturalSorting: naturalSorting.value,
        });
    }

    function getMainListingParams() {
        if (disableRouteParams.value) {
            return {
                limit: limit.value,
                page: page.value,
                term: term.value,
                sortBy: sortBy.value,
                sortDirection: sortDirection.value,
                naturalSorting: naturalSorting.value,
            };
        }

        return {
            limit: route.query.limit,
            page: route.query.page,
            term: route.query.term,
            sortBy: route.query.sortBy || sortBy.value,
            sortDirection: route.query.sortDirection || sortDirection.value,
            naturalSorting: route.query.naturalSorting || naturalSorting.value,
        };
    }

    function updateSelection(updatedSelection: Record<string, unknown>): void {
        selection.value = updatedSelection;
    }

    function onPageChange(options: { page: number; limit: number }): void {
        page.value = options.page;
        limit.value = options.limit;

        if (disableRouteParams.value) {
            getList();
            return;
        }

        updateRoute({ page: page.value });
    }

    function onSearch(value: string | null | undefined): void {
        term.value = value;

        if (disableRouteParams.value) {
            page.value = 1;
            getList();
            return;
        }

        updateRoute({ term: term.value, page: 1 });
    }

    function onSwitchFilter(_filter: ListingFilter, filterIndex: number): void {
        const filter = filters.value[filterIndex];
        if (filter) {
            filter.active = !filter.active;
        }

        page.value = 1;
    }

    function onSort(options: { sortBy: string; sortDirection: string }): void {
        if (disableRouteParams.value) {
            updateData(options);
        } else {
            updateRoute(options);
        }

        getList();
    }

    function onSortColumn(column: { dataIndex: string; naturalSorting: boolean }): void {
        if (disableRouteParams.value) {
            if (sortBy.value === column.dataIndex) {
                sortDirection.value = sortDirection.value === 'ASC' ? 'DESC' : 'ASC';
            } else {
                sortDirection.value = 'ASC';
                sortBy.value = column.dataIndex;
            }
            getList();
            return;
        }

        if (sortBy.value === column.dataIndex) {
            updateRoute({ sortDirection: sortDirection.value === 'ASC' ? 'DESC' : 'ASC' });
        } else {
            naturalSorting.value = column.naturalSorting;
            updateRoute({
                sortBy: column.dataIndex,
                sortDirection: 'ASC',
                naturalSorting: column.naturalSorting,
            });
        }
    }

    function onRefresh(): void {
        getList();
    }

    function isValidTerm(value: string): boolean {
        return searchRankingService?.isValidTerm(value) ?? false;
    }

    async function addQueryScores(value: string, originalCriteria: Criteria): Promise<Criteria> {
        entitySearchable.value = true;
        if (!searchConfigEntity.value || !isValidTerm(value) || !searchRankingService) {
            return originalCriteria;
        }

        const rankingFields = searchRankingService.getSearchFieldsByEntity(searchConfigEntity.value);
        if (Object.keys(rankingFields).length < 1) {
            entitySearchable.value = false;
            return originalCriteria;
        }

        return searchRankingService.buildSearchQueriesForEntity(rankingFields, value, originalCriteria);
    }

    function parseBooleanQueryParams(query: LocationQuery): void {
        Object.keys(query).forEach((key) => {
            if (String(query[key]).toLowerCase() === 'true') {
                (query as Record<string, unknown>)[key] = true;
            } else if (String(query[key]).toLowerCase() === 'false') {
                (query as Record<string, unknown>)[key] = false;
            }
        });
    }

    function updateCriteria(criteria: unknown[]): void {
        page.value = 1;
        filterCriteriaSource.value = criteria;

        if (disableRouteParams.value) {
            getList();
            return;
        }

        updateRoute({ page: 1 });
    }

    function initializeListing(options: InitializeListingOptions = {}): void {
        if (initialized) {
            return;
        }
        initialized = true;

        (Object.keys(state) as (keyof ListingState)[]).forEach((key) => {
            const override = options[key];
            if (override) {
                const mutableState = state as unknown as Record<keyof ListingState, Ref<unknown>>;
                mutableState[key] = override as Ref<unknown>;
            }
        });

        filtersSource = options.filters ?? filtersSource;
        filterCriteriaSource = options.filterCriteria ?? filterCriteriaSource;
        storeKeySource = options.storeKey ?? storeKeySource;
        getListCallback = options.getList ?? getListCallback;

        if (matchedRoute?.value) {
            onBeforeRouteLeave(() => {
                Contena.Store.get('ctBulkEdit').selectedIds = [];
            });
        }

        watch(
            () =>
                [
                    route.name,
                    route.query,
                ] as const,
            (
                [
                    newRouteName,
                    newQuery,
                ],
                [
                    oldRouteName,
                    oldQuery,
                ],
            ) => {
                if (disableRouteParams.value || oldRouteName !== newRouteName) {
                    return;
                }

                if (Contena.Utils.types.isEmpty(newQuery)) {
                    resetListing();
                }

                parseBooleanQueryParams(newQuery);
                updateData(newQuery as unknown as Parameters<typeof updateData>[0]);

                const storeKey = storeKeySource.value;
                if (storeKey && newQuery[storeKey] !== oldQuery[storeKey] && filterCriteriaSource.value.length) {
                    filterCriteriaSource.value = [];
                    return;
                }

                getList();
            },
        );

        watch(selection, (value) => {
            Contena.Store.get('ctBulkEdit').selectedIds = Object.keys(value);
        });
        watch(term, (value) => {
            freshSearchTerm.value = !!value?.length;
        });
        watch(sortBy, () => {
            freshSearchTerm.value = false;
        });
        watch(sortDirection, () => {
            freshSearchTerm.value = false;
        });

        previousRouteName.value = route.name as string;

        if (disableRouteParams.value) {
            getList();
            return;
        }

        const actualQueryParameters = route.query;
        if (Contena.Utils.types.isEmpty(actualQueryParameters)) {
            resetListing();
            return;
        }

        parseBooleanQueryParams(actualQueryParameters);
        updateData(actualQueryParameters as unknown as Parameters<typeof updateData>[0]);
        getList();
    }

    return {
        page,
        limit,
        total,
        sortBy,
        sortDirection,
        naturalSorting,
        selection,
        term,
        disableRouteParams,
        searchConfigEntity,
        entitySearchable,
        freshSearchTerm,
        previousRouteName,
        maxPage,
        routeName,
        selectionArray,
        selectionCount,
        filters,
        searchRankingFields,
        currentSortBy,
        updateData,
        updateRoute,
        resetListing,
        getMainListingParams,
        updateSelection,
        onPageChange,
        onSearch,
        onSwitchFilter,
        onSort,
        onSortColumn,
        onRefresh,
        getList,
        isValidTerm,
        addQueryScores,
        parseBooleanQueryParams,
        updateCriteria,
        initializeListing,
    };
}
