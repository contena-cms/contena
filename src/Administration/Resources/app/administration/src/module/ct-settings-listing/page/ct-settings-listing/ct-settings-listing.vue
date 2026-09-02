<template>
    <ct-block name="ct_settings_listing_index">
        <ct-page class="ct-settings-listing-index">
            <template #language-switch>
                <ct-block name="ct_settings_listing_language_switch">
                    <ct-language-switch @on-change="loadSortings" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_settings_listing_smart_bar_header">
                    <h2>
                        {{ t('ct-settings.index.title') }}
                        <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ t('ct-settings-listing.general.textHeadline') }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_listing_smart_bar_actions">
                    <mt-button variant="primary" :is-loading="isLoading" @click="onSave">
                        {{ t('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_listing_content">
                    <ct-card-view>
                        <ct-block name="ct_settings_listing_content_system_config">
                            <ct-system-config
                                ref="systemConfig"
                                channel-switchable
                                domain="core.listing"
                                @loading-changed="onSystemConfigLoadingChanged"
                            >
                                <template #afterElements="{ config, index, isNotDefaultChannel, inheritance }">
                                    <template v-if="config && index === 0">
                                        <ct-block name="ct_settings_listing_default_sorting">
                                            <ct-inherit-wrapper
                                                v-model:value="config['core.listing.defaultSorting']"
                                                :label="t('ct-settings-listing.general.labelDefaultSorting')"
                                                :has-parent="isNotDefaultChannel"
                                                :inherited-value="inheritance['core.listing.defaultSorting']"
                                                required
                                            >
                                                <template #content="{ isInherited, currentValue, updateCurrentValue }">
                                                    <mt-select
                                                        class="ct-settings-listing-index__default-sorting-select"
                                                        :model-value="currentValue"
                                                        :options="sortingOptions"
                                                        :disabled="isInherited || undefined"
                                                        :placeholder="
                                                            t('ct-settings-listing.general.placeholderDefaultSorting')
                                                        "
                                                        @update:model-value="updateCurrentValue"
                                                    />
                                                </template>
                                            </ct-inherit-wrapper>
                                        </ct-block>

                                        <ct-block name="ct_settings_listing_default_search_sorting">
                                            <ct-inherit-wrapper
                                                v-model:value="config['core.listing.defaultSearchResultSorting']"
                                                :label="t('ct-settings-listing.general.labelDefaultSearchResultSorting')"
                                                :has-parent="isNotDefaultChannel"
                                                :inherited-value="inheritance['core.listing.defaultSearchResultSorting']"
                                                required
                                            >
                                                <template #content="{ isInherited, currentValue, updateCurrentValue }">
                                                    <mt-select
                                                        class="ct-settings-listing-index__default-search-result-sorting-select"
                                                        :model-value="currentValue"
                                                        :options="sortingOptions"
                                                        :disabled="isInherited || undefined"
                                                        :placeholder="
                                                            t(
                                                                'ct-settings-listing.general.placeholderDefaultSearchResultSorting',
                                                            )
                                                        "
                                                        @update:model-value="updateCurrentValue"
                                                    />
                                                </template>
                                            </ct-inherit-wrapper>
                                        </ct-block>
                                    </template>
                                </template>
                            </ct-system-config>
                        </ct-block>

                        <ct-block name="ct_settings_listing_content_sorting_options">
                            <mt-data-table
                                layout="full"
                                :caption="t('ct-settings-listing.index.blogSorting.title')"
                                :data-source="blogSortingOptions"
                                :columns="columns"
                                :is-loading="isSortingLoading"
                                :pagination-total-items="total"
                                :current-page="page"
                                :pagination-limit="limit"
                                :search-value="term"
                                :number-of-results="total"
                                sort-by="priority"
                                sort-direction="DESC"
                                :disable-delete="false"
                                :disable-edit="false"
                                :disable-settings-table="true"
                                @reload="loadSortings"
                                @pagination-current-page-change="onPageChange"
                                @pagination-limit-change="onLimitChange"
                                @search-value-change="onSearch"
                                @open-details="onEdit"
                                @item-delete="onRequestDelete"
                            >
                                <template #toolbar>
                                    <ct-block name="ct_settings_listing_add_sorting">
                                        <mt-button variant="primary" @click="onCreate">
                                            {{ t('ct-settings-listing.index.blogSorting.addButton') }}
                                        </mt-button>
                                    </ct-block>
                                </template>

                                <template #column-fields="{ data }">
                                    <span class="ct-settings-listing-index__criteria">
                                        {{ formatBlogSortingFields(data.fields) }}
                                    </span>
                                </template>
                            </mt-data-table>

                            <mt-empty-state
                                v-if="!isSortingLoading && blogSortingOptions.length === 0"
                                icon="regular-sort"
                                :headline="t('ct-settings-listing.index.blogSorting.emptyState.title')"
                                :description="t('ct-settings-listing.index.blogSorting.emptyState.subline')"
                            />
                        </ct-block>
                    </ct-card-view>

                    <ct-settings-listing-delete-modal
                        v-if="sortingToDelete"
                        :title="t('ct-settings-listing.index.deleteModal.title')"
                        :description="
                            t('ct-settings-listing.index.deleteModal.description', {
                                sortingOptionName: sortingToDelete.label,
                            })
                        "
                        @cancel="sortingToDelete = null"
                        @delete="onConfirmDelete"
                    />
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';

import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';
import './ct-settings-listing.scss';

interface BlogSortingField {
    field: string;
    order: 'asc' | 'desc';
    priority: number;
    naturalSorting: boolean | number | null;
}

interface BlogSortingRow {
    id: string;
    label: string;
    key: string;
    priority: number;
    active: boolean;
    locked: boolean;
    fields: BlogSortingField[];
}

interface SystemConfigComponent {
    actualConfigData: Record<string, Record<string, unknown>>;
    saveAll: () => Promise<unknown>;
}

interface SystemConfigApiService {
    batchSave(values: Record<string, Record<string, unknown>>): Promise<unknown>;
}

interface SystemConfigRow {
    channelId: string | null;
}

interface CustomFieldRow {
    id: string;
    name: string;
    customFieldSetId: string;
    config: { label?: Record<string, string> };
}

interface CustomFieldSetRelationRow {
    customFieldSetId: string;
}

interface TableColumn {
    property: string;
    label: string;
    renderer: 'text' | 'number';
    position: number;
    sortable: boolean;
    clickable?: boolean;
    allowResize: boolean;
}

const { Criteria } = Contena.Data;
defineProps({});
const { t } = useI18n();
const router = useRouter();
const { getInlineSnippet } = useInlineSnippet();
const { createNotificationError, createNotificationSuccess } = useNotification();
const snippetText = (value: unknown): string => (typeof value === 'string' ? value : '');

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const systemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService');
if (!repositoryFactory || !systemConfigApiService) {
    throw new Error('The repository factory or system config service is unavailable.');
}

const blogSortingRepository = repositoryFactory.create('blog_sorting' as keyof EntitySchema.Entities);
const systemConfigRepository = repositoryFactory.create('system_config');
const customFieldRepository = repositoryFactory.create('custom_field');
const customFieldSetRelationRepository = repositoryFactory.create('custom_field_set_relation');
const blogSortingOptions = ref<BlogSortingRow[]>([]);
const sortingToDelete = ref<BlogSortingRow | null>(null);
const isSortingLoading = ref(false);
const isSystemConfigLoading = ref(false);
const page = ref(1);
const limit = ref(10);
const total = ref(0);
const term = ref('');
const customFields = ref<CustomFieldRow[]>([]);
const systemConfig = ref<SystemConfigComponent | null>(null);

const columns = computed<TableColumn[]>(() => [
    {
        property: 'label',
        label: t('ct-settings-listing.index.blogSorting.grid.header.name'),
        renderer: 'text',
        position: 100,
        sortable: true,
        clickable: true,
        allowResize: true,
    },
    {
        property: 'fields',
        label: t('ct-settings-listing.index.blogSorting.grid.header.criteria'),
        renderer: 'text',
        position: 200,
        sortable: false,
        allowResize: true,
    },
    {
        property: 'priority',
        label: t('ct-settings-listing.index.blogSorting.grid.header.priority'),
        renderer: 'number',
        position: 300,
        sortable: true,
        allowResize: true,
    },
]);
const sortingOptions = computed(() =>
    blogSortingOptions.value.map((sorting) => ({ label: sorting.label, value: sorting.id })),
);

const loadSortings = async (): Promise<void> => {
    isSortingLoading.value = true;
    const criteria = new Criteria(page.value, limit.value);
    criteria.addSorting(Criteria.sort('priority', 'DESC'));
    if (term.value) criteria.setTerm(term.value);

    try {
        const result = await blogSortingRepository.search(criteria, Contena.Context.api);
        blogSortingOptions.value = [...result] as unknown as BlogSortingRow[];
        total.value = result.total;
    } finally {
        isSortingLoading.value = false;
    }
};
const loadCustomFields = async (): Promise<void> => {
    const relationCriteria = new Criteria(1, 25);
    relationCriteria.addFilter(Criteria.equals('entityName', 'blog'));
    const relations = await customFieldSetRelationRepository.search(relationCriteria, Contena.Context.api);
    const customFieldSetIds = [...relations].map(
        (relation) => (relation as unknown as CustomFieldSetRelationRow).customFieldSetId,
    );

    const customFieldCriteria = new Criteria(1, 100);
    customFieldCriteria.addFilter(
        Criteria.not('and', [
            Criteria.equalsAny('type', [
                'price',
                'json',
                'text',
                'html',
            ]),
        ]),
    );
    customFieldCriteria.addFilter(
        customFieldSetIds.length ? Criteria.equalsAny('customFieldSetId', customFieldSetIds) : Criteria.equals('id', null),
    );
    const result = await customFieldRepository.search(customFieldCriteria, Contena.Context.api);
    customFields.value = [...result] as unknown as CustomFieldRow[];
};
const setDefaultSortingsActive = async (): Promise<void> => {
    const defaultSortingIds = new Set<string>();
    Object.values(systemConfig.value?.actualConfigData ?? {}).forEach((config) => {
        const listingSorting = config['core.listing.defaultSorting'];
        const searchSorting = config['core.listing.defaultSearchResultSorting'];
        if (typeof listingSorting === 'string') defaultSortingIds.add(listingSorting);
        if (typeof searchSorting === 'string') defaultSortingIds.add(searchSorting);
    });

    await Promise.all(
        blogSortingOptions.value
            .filter((sorting) => defaultSortingIds.has(sorting.id) && !sorting.active)
            .map((sorting) => {
                sorting.active = true;
                return blogSortingRepository.save(
                    sorting as unknown as Entity<keyof EntitySchema.Entities>,
                    Contena.Context.api,
                );
            }),
    );
};
const isDefaultSorting = (sortingId: string): boolean =>
    Object.values(systemConfig.value?.actualConfigData ?? {}).some(
        (config) =>
            config['core.listing.defaultSorting'] === sortingId ||
            config['core.listing.defaultSearchResultSorting'] === sortingId,
    );

const onSave = async (): Promise<void> => {
    if (!systemConfig.value) return;

    const globalConfig = systemConfig.value.actualConfigData.null;
    if (!globalConfig?.['core.listing.defaultSorting'] || !globalConfig['core.listing.defaultSearchResultSorting']) {
        createNotificationError({ message: t('ct-settings-listing.general.messageSaveDefaultValuesEmpty') });
        return;
    }

    try {
        await Promise.all([
            systemConfig.value.saveAll(),
            setDefaultSortingsActive(),
        ]);
        createNotificationSuccess({ message: t('ct-settings-listing.general.messageSaveSuccess') });
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : 'Unknown error';
        createNotificationError({ message: t('ct-settings-listing.general.messageSaveError', { message }) });
    }
};

const onCreate = (): void => {
    void router.push({ name: 'ct.settings.listing.create' });
};
const onEdit = (sorting: BlogSortingRow): void => {
    void router.push({ name: 'ct.settings.listing.edit', params: { id: sorting.id } });
};
const onRequestDelete = (sorting: BlogSortingRow): void => {
    if (sorting.locked || isDefaultSorting(sorting.id)) return;
    sortingToDelete.value = sorting;
};
const onConfirmDelete = async (): Promise<void> => {
    const sorting = sortingToDelete.value;
    if (!sorting) return;

    sortingToDelete.value = null;
    try {
        const configCriteria = new Criteria();
        configCriteria.addFilter(
            Criteria.equalsAny('configurationKey', [
                'core.listing.defaultSorting',
                'core.listing.defaultSearchResultSorting',
            ]),
        );
        configCriteria.addFilter(Criteria.equals('configurationValue', sorting.id));
        const configEntries = await systemConfigRepository.search(configCriteria, Contena.Context.api);
        const configReset: Record<string, Record<string, unknown>> = {};
        for (const entry of configEntries) {
            const row = entry as unknown as SystemConfigRow & {
                configurationKey: string;
            };
            const channelKey = String(row.channelId);
            configReset[channelKey] ??= {};
            configReset[channelKey][row.configurationKey] = null;
        }
        if (Object.keys(configReset).length > 0) {
            await systemConfigApiService.batchSave(configReset);
        }

        Object.values(systemConfig.value?.actualConfigData ?? {}).forEach((config) => {
            if (config['core.listing.defaultSorting'] === sorting.id) {
                config['core.listing.defaultSorting'] = null;
            }
            if (config['core.listing.defaultSearchResultSorting'] === sorting.id) {
                config['core.listing.defaultSearchResultSorting'] = null;
            }
        });

        await blogSortingRepository.delete(sorting.id, Contena.Context.api);
        if (page.value > 1 && blogSortingOptions.value.length === 1) {
            page.value -= 1;
        }
        await loadSortings();
    } catch {
        createNotificationError({ message: t('ct-settings-listing.index.blogSorting.messageDeleteError') });
    }
};
const onPageChange = (nextPage: number): void => {
    page.value = nextPage;
    void loadSortings();
};
const onLimitChange = (nextLimit: number): void => {
    page.value = 1;
    limit.value = nextLimit;
    void loadSortings();
};
const onSearch = (searchTerm: string): void => {
    page.value = 1;
    term.value = searchTerm;
    void loadSortings();
};
const onSystemConfigLoadingChanged = (loading: boolean): void => {
    isSystemConfigLoading.value = loading;
};
const formatBlogSortingFields = (fields: BlogSortingField[]): string =>
    fields
        .map((field) => {
            if (field.field.startsWith('customFields.')) {
                const technicalName = field.field.replace(/^customFields\./, '');
                const customField = customFields.value.find((entry) => entry.name === technicalName);
                return snippetText(getInlineSnippet(customField?.config.label ?? {})) || technicalName;
            }

            const translationKey = `ct-settings-listing.general.blogSortingCriteriaGrid.options.label.${field.field}`;
            return `${t(translationKey)} ${t(`global.default.${field.order === 'asc' ? 'ascending' : 'descending'}`)}`;
        })
        .join(', ');

void Promise.all([
    loadSortings(),
    loadCustomFields(),
]);

ctDefinePublic({
    blogSortingOptions,
    sortingToDelete,
    isSortingLoading,
    isSystemConfigLoading,
    page,
    limit,
    total,
    term,
    customFields,
    systemConfig,
    columns,
    sortingOptions,
    loadSortings,
    loadCustomFields,
    setDefaultSortingsActive,
    isDefaultSorting,
    onSave,
    onCreate,
    onEdit,
    onRequestDelete,
    onConfirmDelete,
    onPageChange,
    onLimitChange,
    onSearch,
    onSystemConfigLoadingChanged,
    formatBlogSortingFields,
});

const isLoading = computed(() => isSortingLoading.value || isSystemConfigLoading.value);
usePageTitle();

defineExpose({
    blogSortingOptions,
    sortingToDelete,
    isLoading,
    page,
    limit,
    total,
    term,
    customFields,
    systemConfig,
    columns,
    sortingOptions,
    loadSortings,
    loadCustomFields,
    setDefaultSortingsActive,
    isDefaultSorting,
    onSave,
    onCreate,
    onEdit,
    onRequestDelete,
    onConfirmDelete,
    onPageChange,
    onLimitChange,
    onSearch,
    onSystemConfigLoadingChanged,
    formatBlogSortingFields,
});
</script>
