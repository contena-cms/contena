<template>
    <ct-block name="sw_settings_snippet_grid_sidebar">
        <aside class="ct-snippet-settings__sidebar">
            <ct-block name="sw_settings_snippet_grid_sidebar_header">
                <div class="ct-snippet-settings__sidebar-header">
                    <strong>{{ t('ct-settings-snippet.list.titleSidebarItemFilter') }}</strong>
                    <mt-badge v-if="activeFilterNumber" variant="info">{{ activeFilterNumber }}</mt-badge>
                    <mt-button
                        variant="secondary"
                        square
                        :title="t('ct-settings-snippet.list.titleSidebarItemRefresh')"
                        @click="onRefresh"
                    >
                        <mt-icon name="regular-undo" size="16px" />
                    </mt-button>
                </div>
            </ct-block>

            <ct-block name="sw_settings_snippet_grid_sidebar_filter_actions">
                <mt-button
                    v-if="activeFilterNumber"
                    class="ct-snippet-settings__sidebar-reset-all"
                    variant="secondary"
                    size="small"
                    @click="resetAll"
                >
                    {{ t('ct-sidebar-filter-panel.resetButton') }}
                </mt-button>
            </ct-block>

            <div class="ct-snippet-settings__sidebar-body">
                <ct-settings-snippet-filter-switch
                    name="emptySnippets"
                    group="emptySnippets"
                    :value="filterSettings?.emptySnippets"
                    :label="t('ct-settings-snippet.filter.showOnlyEmpty')"
                    @update:value="onChange"
                />
                <ct-settings-snippet-filter-switch
                    name="editedSnippets"
                    group="editedSnippets"
                    :value="filterSettings?.editedSnippets"
                    :label="t('ct-settings-snippet.filter.showOnlyCustom')"
                    @update:value="onChange"
                />
                <ct-settings-snippet-filter-switch
                    name="addedSnippets"
                    group="addedSnippets"
                    :value="filterSettings?.addedSnippets"
                    :label="t('ct-settings-snippet.filter.showOnlyAdded')"
                    @update:value="onChange"
                />

                <ct-block name="sw_settings_snippet_grid_sidebar_filter_author">
                    <section v-if="authorFilters.length" class="ct-snippet-settings__filter-group">
                        <h3>{{ t('ct-settings-snippet.filter.author') }}</h3>
                        <ct-settings-snippet-filter-switch
                            v-for="item in authorFilters"
                            :key="item"
                            group="authorFilter"
                            :name="item"
                            :value="filterSettings?.[item]"
                            :label="item"
                            @update:value="onChange"
                        />
                    </section>
                </ct-block>

                <ct-block name="sw_settings_snippet_grid_sidebar_filter_more">
                    <section v-if="filterItems.length" class="ct-snippet-settings__filter-group">
                        <h3>{{ t('ct-settings-snippet.filter.more') }}</h3>
                        <ct-settings-snippet-filter-switch
                            v-for="item in filterItems"
                            :key="item"
                            group="namespaceFilters"
                            :name="item"
                            :value="filterSettings?.[item]"
                            :label="item"
                            @update:value="onChange"
                        />
                    </section>
                </ct-block>
            </div>
        </aside>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-settings-snippet-sidebar.scss';

type FilterSettings = Record<string, boolean>;
type FilterChange = { value: boolean; name: string; group: string | null };
type FilterSidebarItem = {
    isActive: boolean;
    closeContent?: () => void;
    openContent?: () => void;
};

defineOptions({
    shortcuts: {
        OF: 'openFilterSidebar',
    },
});

const props = withDefaults(
    defineProps<{
        filterItems: string[];
        authorFilters: string[];
        filterSettings?: FilterSettings | null;
    }>(),
    { filterSettings: null },
);
const emit = defineEmits<{
    change: [field: FilterChange];
    'ct-sidebar-collaps-refresh-grid': [];
    'sidebar-reset-all': [];
    'ct-sidebar-close': [];
    'ct-sidebar-open': [];
}>();
const { t } = useI18n();

const filterSidebarItem = ref<FilterSidebarItem | null>(null);
const activeFilterNumber = computed(() => Object.values(props.filterSettings ?? {}).filter((value) => value).length);
const isExpandedAuthorFilters = computed(() => props.authorFilters.some((item) => props.filterSettings?.[item] === true));
const isExpandedMoreFilters = computed(() => props.filterItems.some((item) => props.filterSettings?.[item] === true));
const registerFilterSidebarItem = (sidebarItem: FilterSidebarItem): void => {
    filterSidebarItem.value = sidebarItem;
};
const closeContent = (): void => {
    if (filterSidebarItem.value?.isActive) {
        emit('ct-sidebar-open');
        return;
    }
    filterSidebarItem.value?.closeContent?.();
    emit('ct-sidebar-close');
};
const openFilterSidebar = (): void => {
    if (filterSidebarItem.value?.isActive) return;
    filterSidebarItem.value?.openContent?.();
    emit('ct-sidebar-open');
};
const onChange = (field: FilterChange): void => emit('change', field);
const onRefresh = (): void => emit('ct-sidebar-collaps-refresh-grid');
const resetAll = (): void => emit('sidebar-reset-all');

swDefinePublic({
    filterSidebarItem,
    activeFilterNumber,
    isExpandedAuthorFilters,
    isExpandedMoreFilters,
    registerFilterSidebarItem,
    closeContent,
    openFilterSidebar,
    onChange,
    onRefresh,
    resetAll,
});

defineExpose({
    filterSidebarItem,
    activeFilterNumber,
    isExpandedAuthorFilters,
    isExpandedMoreFilters,
    registerFilterSidebarItem,
    closeContent,
    openFilterSidebar,
    onChange,
    onRefresh,
    resetAll,
});
</script>
