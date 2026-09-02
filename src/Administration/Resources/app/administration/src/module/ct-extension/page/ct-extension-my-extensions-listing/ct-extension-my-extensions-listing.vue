<template>
    <div class="ct-extension-my-extensions-listing">
        <ct-skeleton v-if="isLoading" variant="extension-list" />

        <div v-else class="ct-extension-my-extensions-listing__listing-grid">
            <mt-banner
                v-if="extensionManagementDisabled"
                class="ct-extension-my-extensions-listing__runtime-extension-warning"
                variant="attention"
                :title="$t('ct-extension.component.ct-extension-my-extensions-listing.alertExtensionManagement.title')"
            >
                <div>
                    {{
                        $t('ct-extension.component.ct-extension-my-extensions-listing.alertExtensionManagement.description')
                    }}
                </div>
            </mt-banner>

            <ct-block name="ct_extension_my_extensions_list_empty_state">
                <mt-empty-state
                    v-if="!extensionListPaginated.length && !filterByActiveState"
                    class="ct-extension-my-extensions-listing__empty-state"
                    icon="regular-plug"
                    :headline="$t('ct-extension.my-extensions.listing.emptyTitle')"
                />
            </ct-block>

            <template v-if="!extensionListPaginated.length && !filterByActiveState"
                ><!-- Keeps the conditional chain connected across ct-block. --></template
            >
            <template v-else>
                <div class="ct-extension-my-extensions-listing__container">
                    <ct-extension-my-extensions-listing-controls
                        :sorting-option="sortingOption"
                        @update:active-state="changeActiveState"
                        @update:sorting-option="changeSortingOption"
                    />

                    <mt-empty-state
                        v-if="!extensionListPaginated.length && filterByActiveState"
                        class="ct-extension-my-extensions-listing__empty-state"
                        icon="regular-plug"
                        :headline="$t('ct-extension.my-extensions.listing.noActivePlugins')"
                    />

                    <template v-else>
                        <template v-for="entry in extensionListPaginated" :key="entry.name">
                            <ct-extension-card-base :extension="entry" @update-list="updateList" />
                        </template>

                        <ct-pagination :total="total" :limit="limit" :page="page" @page-change="changePage" />
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import './ct-extension-my-extensions-listing.scss';

defineProps({});

import { ref, computed, inject, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const $route = useRoute();

const contenaExtensionService = inject('contenaExtensionService');

const filterByActiveState = ref(false);

const isLoading = computed(() => {
    const state = Contena.Store.get('contenaExtensions');

    return state.myExtensions.loading;
});
const myExtensions = computed(() => {
    return Contena.Store.get('contenaExtensions').myExtensions.data;
});
const extensionList = computed(() => {
    const sortedExtensions = sortExtensions([...myExtensions.value], sortingOption.value);

    if (filterByActiveState.value) {
        return filterExtensionsByActiveState(sortedExtensions);
    }

    return sortedExtensions;
});
const extensionListPaginated = computed(() => {
    const begin = (page.value - 1) * limit.value;

    return extensionListSearched.value.slice(begin, begin + limit.value);
});
const extensionListSearched = computed(() => {
    return extensionList.value.filter((extension) => {
        const searchTerm = term.value && term.value.toLowerCase();
        if (!term.value) {
            return true;
        }

        const label = extension.label || '';
        const name = extension.name || '';

        return label.toLowerCase().includes(searchTerm) || name.toLowerCase().includes(searchTerm);
    });
});
const total = computed(() => {
    return extensionListSearched.value.length || 0;
});
const limit = computed({
    get: () => {
        return Number($route.query.limit) || 25;
    },
    set: (newLimit) => {
        updateRouteQuery({ limit: newLimit });
    },
});
const page = computed({
    get: () => {
        return Number($route.query.page) || 1;
    },
    set: (newPage) => {
        updateRouteQuery({ page: newPage });
    },
});
const term = computed({
    get: () => {
        return $route.query.term || undefined;
    },
    set: (newTerm) => {
        updateRouteQuery({
            term: newTerm,
            page: 1,
        });
    },
});
const sortingOption = computed({
    get: () => {
        const sorting = $route.query.sorting;

        return [
            'updated-at',
            'name-asc',
            'name-desc',
        ].includes(sorting)
            ? sorting
            : 'updated-at';
    },
    set: (newSorting) => {
        updateRouteQuery({ sorting: newSorting });
    },
});
const extensionManagementDisabled = computed(() => {
    return Contena.Store.get('context').app.config.settings?.disableExtensionManagement;
});

const mountedComponent = () => {
    updateList();
    updateRouteQuery();
};
const updateList = () => {
    contenaExtensionService.updateExtensionData();
};
const updateRouteQuery = (query = {}) => {
    const routeQuery = $route.query;
    const limit = query.limit || $route.query.limit;
    const page = query.page || $route.query.page;
    const term = query.term || $route.query.term;
    const sorting = query.sorting || $route.query.sorting;

    // Create new route
    const route = {
        name: $route.name,
        params: $route.params,
        query: {
            limit: limit || 25,
            page: page || 1,
            term: term || undefined,
            sorting: sorting || 'updated-at',
        },
    };

    // If query is empty then replace route, otherwise push
    if (Contena.Utils.types.isEmpty(routeQuery)) {
        void router.replace(route);
    } else {
        void router.push(route);
    }
};
const changePage = ({ page, limit }) => {
    updateRouteQuery({ page, limit });
};
const sortExtensions = (extensions, sortingOption) => {
    return extensions.sort((firstExtension, secondExtension) => {
        if (sortingOption === 'name-asc') {
            return firstExtension.label.localeCompare(secondExtension.label, { sensitivity: 'base' });
        }

        if (sortingOption === 'name-desc') {
            return firstExtension.label.localeCompare(secondExtension.label, { sensitivity: 'base' }) * -1;
        }

        if (sortingOption === 'updated-at') {
            if (firstExtension.updatedAt === null && secondExtension.updatedAt !== null) {
                return 1;
            }

            if (firstExtension.updatedAt !== null && secondExtension.updatedAt === null) {
                return -1;
            }

            if (secondExtension.updatedAt === null && firstExtension.updatedAt === null) {
                return 0;
            }

            const firstExtensionDate = new Date(firstExtension.updatedAt.date);
            const secondExtensionDate = new Date(secondExtension.updatedAt.date);

            if (firstExtensionDate > secondExtensionDate) {
                return -1;
            }

            if (firstExtensionDate < secondExtensionDate) {
                return 1;
            }

            if (firstExtensionDate === secondExtensionDate) {
                return 0;
            }
        }

        return 0;
    });
};
const changeSortingOption = (value) => {
    sortingOption.value = value;
};
const changeActiveState = (value) => {
    filterByActiveState.value = value;
};
const filterExtensionsByActiveState = (extensions) => {
    return extensions.filter((extension) => {
        return extension.active;
    });
};

onMounted(() => {
    mountedComponent();
});

ctDefinePublic({
    contenaExtensionService,
    filterByActiveState,
    sortingOption,
    isLoading,
    myExtensions,
    extensionList,
    extensionListPaginated,
    extensionListSearched,
    total,
    limit,
    page,
    term,
    extensionManagementDisabled,
    mountedComponent,
    updateList,
    updateRouteQuery,
    changePage,
    sortExtensions,
    changeSortingOption,
    changeActiveState,
    filterExtensionsByActiveState,
});

defineExpose({
    contenaExtensionService,
    filterByActiveState,
    sortingOption,
    isLoading,
    myExtensions,
    extensionList,
    extensionListPaginated,
    extensionListSearched,
    total,
    limit,
    page,
    term,
    extensionManagementDisabled,
    mountedComponent,
    updateList,
    updateRouteQuery,
    changePage,
    sortExtensions,
    changeSortingOption,
    changeActiveState,
    filterExtensionsByActiveState,
});
</script>
