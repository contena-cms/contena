<template>
    <ct-block name="ct_extension_my_extensions_index">
        <ct-meteor-page class="ct-extension-my-extensions-index" hide-icon>
            <template #smart-bar-header>
                <ct-block name="ct_extension_my_extensions_index_smart_bar_header">
                    {{ $t('ct-extension.mainMenu.plugins') }}
                </ct-block>
            </template>

            <template #search-bar>
                <ct-block name="ct_extension_my_extensions_index_smart_bar_search_slot">
                    <ct-block name="ct_extension_my_extensions_index_smart_bar_search_slot_search_bar">
                        <mt-search
                            :model-value="searchValue"
                            :placeholder="$t('ct-extension.my-extensions.listing.placeholderSearchBar')"
                            @change="onSearch"
                        />
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_extension_my_extensions_index_smart_bar_actions">
                    <ct-block name="ct_extension_my_extensions_index_smart_bar_actions_file_upload">
                        <ct-extension-file-upload v-if="acl.can('system.plugin_upload') && !extensionManagementDisabled" />
                    </ct-block>
                </ct-block>
            </template>

            <template #default>
                <ct-block name="ct_extension_my_extensions_index_body">
                    <router-view />
                </ct-block>
            </template>
        </ct-meteor-page>
    </ct-block>
</template>

<script setup>
defineProps({});

import { computed, inject } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const $route = useRoute();

const acl = inject('acl');

const searchValue = computed({
    get: () => {
        return $route.query.term || '';
    },
    set: (newTerm) => {
        updateRouteQueryTerm(newTerm);
    },
});
const queryParams = computed(() => {
    return {
        term: searchValue.value || undefined,
        limit: $route.query.limit,
        page: 1,
        sorting: $route.query.sorting,
    };
});
const extensionManagementDisabled = computed(() => {
    return Contena.Store.get('context').app.config.settings?.disableExtensionManagement;
});

const onSearch = (term) => {
    searchValue.value = term;
};
const updateRouteQueryTerm = (term) => {
    const routeQuery = $route.query;

    // Create new route
    const route = {
        name: $route.name,
        params: $route.params,
        query: {
            term: term || undefined,
            limit: $route.query.limit,
            page: 1,
            sorting: $route.query.sorting,
        },
    };

    // If query is empty then replace route, otherwise push
    if (Contena.Utils.types.isEmpty(routeQuery)) {
        void router.replace(route);
    } else {
        void router.push(route);
    }
};

ctDefinePublic({
    acl,
    searchValue,
    queryParams,
    extensionManagementDisabled,
    onSearch,
    updateRouteQueryTerm,
});

defineExpose({
    acl,
    searchValue,
    queryParams,
    extensionManagementDisabled,
    onSearch,
    updateRouteQueryTerm,
});
</script>
