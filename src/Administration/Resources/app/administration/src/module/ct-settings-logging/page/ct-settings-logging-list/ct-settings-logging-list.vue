<template>
    <ct-block name="sw_settings_list">
        <ct-page class="ct-settings-logging-list">
            <template #search-bar>
                <ct-block name="sw_settings_logging_list_search_bar">
                    <mt-search
                        :placeholder="translate('ct-settings-logging.general.placeholderSearchBar')"
                        :model-value="term"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_settings_logging_list_smart_bar_header">
                    <ct-block name="sw_settings_logging_list_smart_bar_header_title">
                        <h2>
                            <ct-block name="sw_settings_logging_list_smart_bar_header_title_text">
                                {{ translate('ct-settings.index.title') }}
                                <mt-icon name="regular-chevron-right-xs" size="12px" />
                                {{ translate('ct-settings-logging.list.title') }}
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_logging_list_smart_bar_actions">
                    <mt-button variant="secondary" size="default" @click="onRefresh">
                        <mt-icon name="regular-undo" size="16" />
                        {{ translate('ct-settings-logging.list.titleSidebarItemRefresh') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_logging_list_content">
                    <ct-block name="sw_settings_logging_list_content_listing">
                        <mt-data-table
                            layout="full"
                            :data-source="logs"
                            :columns="logColumns"
                            :caption="translate('ct-settings-logging.list.title')"
                            :sort-by="sortBy"
                            :sort-direction="sortDirection"
                            :is-loading="isLoading"
                            :current-page="page"
                            :pagination-limit="limit"
                            :pagination-total-items="total"
                            disable-search
                            disable-edit
                            disable-delete
                            :additional-context-buttons="additionalContextButtons"
                            @reload="getList"
                            @pagination-current-page-change="onPageChange"
                            @pagination-limit-change="onLimitChange"
                            @sort-change="onSort"
                            @context-select="onContextSelect"
                        >
                            <template #column-createdAt="{ data: item }">
                                <ct-block name="sw_settings_logging_list_column_created_at">
                                    <ct-time-ago :date="item.createdAt" />
                                </ct-block>
                            </template>

                            <!-- ct-block preserves this slot variable at runtime. -->
                            <!-- eslint-disable vue/no-unused-vars -->
                            <template #column-level="{ data: item }">
                                <ct-block name="sw_settings_logging_list_column_level">
                                    {{ logLevelToString(item.level) }} ({{ item.level }})
                                </ct-block>
                            </template>
                            <!-- eslint-enable vue/no-unused-vars -->

                            <template #column-context="{ data: item }">
                                <ct-block name="sw_settings_logging_list_column_context">
                                    <a
                                        role="button"
                                        tabindex="0"
                                        @click="showInfoModal(item)"
                                        @keydown.enter="showInfoModal(item)"
                                    >
                                        {{ item.context }}
                                    </a>
                                </ct-block>
                            </template>
                        </mt-data-table>
                    </ct-block>

                    <ct-block name="sw_settings_logging_list_content_info_modal">
                        <component
                            :is="modalNameFromLogEntry"
                            v-if="displayedLog !== null"
                            class="ct-settings-logging-list__custom-content"
                            :log-entry="displayedLog"
                            @close="closeInfoModal"
                        />
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-logging-list.scss';
const { Component } = Contena;
const { Criteria } = Contena.Data;

defineProps({});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { useSettingsListing } from 'src/app/composables/use-settings-listing';

const { t } = useI18n();
const { page, limit, total, term, onPageChange, onSearch, onRefresh, initializeSettingsListing } = useSettingsListing();

const translate = t;

usePageTitle();

const repositoryFactory = inject('repositoryFactory');

const entityName = ref('log_entry');
const sortBy = ref('log_entry.createdAt');
const sortDirection = ref('DESC');
const isLoading = ref(true);
const logs = ref([]);
const displayedLog = ref(null);
const logLevels = ref({
    Debug: 100,
    Info: 200,
    Notice: 250,
    Warning: 300,
    Error: 400,
    Critical: 500,
    Alert: 550,
    Emergency: 600,
});

const logEntryRepository = computed(() => {
    return repositoryFactory.create('log_entry');
});
const logColumns = computed(() => {
    return getLogColumns();
});
const additionalContextButtons = computed(() => [
    { key: 'showInfo', label: t('ct-settings-logging.list.actionShowInfo') },
]);
const modalNameFromLogEntry = computed(() => {
    const eventName = displayedLog.value?.message ?? '';
    const subComponentName = eventName.replace(/[._]/g, '-');

    if (Component.getComponentRegistry().has(`ct-settings-logging-${subComponentName}-info`)) {
        return `ct-settings-logging-${subComponentName}-info`;
    }

    return 'ct-settings-logging-entry-info';
});

const showInfoModal = (entryContents) => {
    displayedLog.value = entryContents;
};
const closeInfoModal = () => {
    displayedLog.value = null;
};
const onLimitChange = (nextLimit) => {
    limit.value = nextLimit;
    page.value = 1;
    void getList();
};
const onSort = ({ sortBy: nextSortBy, sortDirection: nextSortDirection }) => {
    sortBy.value = nextSortBy;
    sortDirection.value = nextSortDirection;
    page.value = 1;
    void getList();
};
const onContextSelect = ({ key, data }) => {
    if (key === 'showInfo') showInfoModal(data);
};
const getList = () => {
    isLoading.value = true;

    const criteria = new Criteria(page.value, limit.value);

    criteria.setTerm(term.value);
    criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));

    return logEntryRepository.value
        .search(criteria)
        .then((response) => {
            total.value = response.total;
            logs.value = response;
            isLoading.value = false;

            return response;
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const logLevelToString = (level) => {
    const distances = Object.values(logLevels.value).map((x) => {
        return Math.abs(x - level);
    });

    const stringLevel = Object.keys(logLevels.value)[
        distances.findIndex((x) => {
            return x === Math.min(...distances);
        })
    ];

    return t(`ct-settings-logging.list.level${stringLevel}`);
};
const getLogColumns = () => {
    return [
        {
            property: 'createdAt',
            dataIndex: 'createdAt',
            label: t('ct-settings-logging.list.columnDate'),
            renderer: 'text',
            allowResize: true,
            primary: true,
        },
        {
            property: 'message',
            dataIndex: 'message',
            label: t('ct-settings-logging.list.columnMessage'),
            renderer: 'text',
            allowResize: true,
        },
        {
            property: 'level',
            dataIndex: 'level',
            label: t('ct-settings-logging.list.columnLevel'),
            renderer: 'text',
            allowResize: true,
        },
        {
            property: 'context',
            dataIndex: 'context',
            label: t('ct-settings-logging.list.columnContent'),
            renderer: 'text',
            allowResize: true,
        },
    ];
};

initializeSettingsListing({
    getList,
    sortBy,
    sortDirection,
    entityName,
    isLoading,
});

swDefinePublic({
    repositoryFactory,
    entityName,
    sortBy,
    sortDirection,
    isLoading,
    logs,
    displayedLog,
    logLevels,
    logEntryRepository,
    logColumns,
    additionalContextButtons,
    modalNameFromLogEntry,
    showInfoModal,
    closeInfoModal,
    onLimitChange,
    onSort,
    onContextSelect,
    getList,
    logLevelToString,
    getLogColumns,
});

defineExpose({
    repositoryFactory,
    entityName,
    sortBy,
    sortDirection,
    isLoading,
    logs,
    displayedLog,
    logLevels,
    logEntryRepository,
    logColumns,
    additionalContextButtons,
    modalNameFromLogEntry,
    showInfoModal,
    closeInfoModal,
    onLimitChange,
    onSort,
    onContextSelect,
    getList,
    logLevelToString,
    getLogColumns,
});
</script>
