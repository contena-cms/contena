<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="sw_settings_search_excluded_search_terms">
        <mt-card
            class="ct-settings-search-excluded-search-terms"
            position-identifier="ct-settings-search-excluded-search-terms"
            :title="t('ct-settings-search.generalTab.labelExcludedSearchTerms')"
        >
            <mt-empty-state
                v-if="showEmptyState"
                icon="regular-search"
                :headline="t('ct-settings-search.generalTab.textEmptyStateExcludedSearchTerms')"
                :description="t('ct-settings-search.generalTab.textEmptyStateExcludedSearchTermsDescription')"
            >
                <template #button>
                    <mt-button
                        class="ct-settings-search-excluded-search-terms__action-add"
                        size="small"
                        variant="secondary"
                        :disabled="!acl.can('blog_search_config.creator') || undefined"
                        @click="addExcludedSearchTerms"
                    >
                        {{ t('ct-settings-search.generalTab.buttonAddExcludedSearch') }}
                    </mt-button>
                </template>
            </mt-empty-state>

            <div v-else>
                <div class="ct-settings-search-excluded-search-terms__header-bar">
                    <mt-search
                        v-model="searchTerm"
                        :placeholder="t('ct-settings-search.generalTab.textPlaceholderTermsFilter')"
                        @change="onSearchTermChange"
                    />
                    <div class="ct-settings-search-excluded-search-terms__actions">
                        <mt-button
                            class="ct-settings-search-excluded-search-terms__insert-button"
                            size="small"
                            variant="secondary"
                            :disabled="!acl.can('blog_search_config.creator') || undefined"
                            @click="onInsertTerm"
                        >
                            {{ t('ct-settings-search.generalTab.buttonAddExcludedSearchTerms') }}
                        </mt-button>
                        <mt-button
                            class="ct-settings-search-excluded-search-terms__reset-button"
                            size="small"
                            variant="critical"
                            :disabled="!acl.can('blog_search_config.creator') || undefined"
                            :is-loading="isLoading"
                            @click="onResetExcludedSearchTermDefault"
                        >
                            {{ t('ct-settings-search.generalTab.buttonResetDefault') }}
                        </mt-button>
                    </div>
                </div>

                <mt-data-table
                    v-if="items.length"
                    class="ct-settings-search__grid"
                    :data-source="items"
                    :columns="columns"
                    :is-loading="isLoading || isExcludedTermsLoading"
                    :current-page="page"
                    :pagination-limit="limit"
                    :pagination-total-items="total"
                    :allow-row-selection="acl.can('blog_search_config.deleter')"
                    :allow-bulk-delete="acl.can('blog_search_config.deleter')"
                    :disable-edit="true"
                    :disable-delete="true"
                    :disable-search="true"
                    :disable-settings-table="true"
                    :caption="t('ct-settings-search.generalTab.labelExcludedSearchTerms')"
                    @pagination-current-page-change="onPageChange"
                    @pagination-limit-change="onLimitChange"
                    @selection-change="selectionChanged"
                    @bulk-delete="onBulkDeleteExcludedTerm"
                >
                    <template #column-value="{ data }">
                        <mt-text-field
                            :model-value="data.value"
                            size="small"
                            :disabled="!acl.can('blog_search_config.editor') || undefined"
                            @update:model-value="data.value = $event"
                            @change="onSaveEdit(data)"
                        />
                    </template>
                    <template #column-actions="{ data }">
                        <mt-button
                            v-tooltip="t('global.default.delete')"
                            square
                            variant="critical"
                            :disabled="!acl.can('blog_search_config.deleter') || undefined"
                            @click="onDeleteExcludedTerm([data])"
                        >
                            <mt-icon name="regular-trash" size="16px" />
                        </mt-button>
                    </template>
                </mt-data-table>
                <mt-empty-state
                    v-else
                    icon="regular-search"
                    :headline="t('ct-settings-search.generalTab.labelExcludedSearchTermsNoResults')"
                    :description="t('ct-settings-search.generalTab.labelExcludedSearchTermsNoResultsDescription')"
                />
            </div>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
/* global Entity */
/* global Entity */
import { computed, inject, ref, watch, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type ExcludedSearchTermApiService from 'src/core/service/api/excluded-search-term.api.service';
import { useNotification } from 'src/app/composables/use-notification';

import './ct-settings-search-excluded-search-terms.scss';

type TermRow = { id: string; value: string; originalValue: string };
type Column = { property: string; label: string; renderer: 'text'; position: number; sortable: boolean; width?: number };
const props = defineProps({
    searchConfigs: { type: Object as PropType<Entity<'blog_search_config'> | null>, default: null },
    isExcludedTermsLoading: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'edit-change': [editing: boolean]; 'data-load': [] }>();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const excludedSearchTermService = inject<ExcludedSearchTermApiService>('excludedSearchTermService');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !excludedSearchTermService || !acl)
    throw new Error('Excluded search term dependencies are unavailable.');

const items = ref<TermRow[]>([]);
const originalItems = ref<string[]>([]);
const showEmptyState = ref(false);
const page = ref(1);
const limit = ref(10);
const total = ref(0);
const searchTerm = ref('');
const isLoading = ref(false);
const isAddingItem = ref(false);
const selection = ref<TermRow[]>([]);
const repository = repositoryFactory.create('blog_search_config');
const columns = computed<Column[]>(() => [
    {
        property: 'value',
        label: t('ct-settings-search.generalTab.textColumnSearchTerm'),
        renderer: 'text',
        position: 100,
        sortable: false,
    },
    {
        property: 'actions',
        label: t('global.default.actions'),
        renderer: 'text',
        position: 200,
        sortable: false,
        width: 80,
    },
]);
const resetData = (): void => {
    originalItems.value = [];
    items.value = [];
    page.value = 1;
    total.value = 0;
};
const filterItems = (): string[] => originalItems.value.filter((term) => term.includes(searchTerm.value));
const sliceItems = (values: string[]): TermRow[] => {
    const offset = (page.value - 1) * limit.value;
    return values
        .slice(offset, offset + limit.value)
        .map((value, index) => ({ id: `${offset + index}-${value}`, value, originalValue: value }));
};
const renderComponent = (): void => {
    const all = filterItems();
    total.value = all.length;
    if (page.value > 1 && (page.value - 1) * limit.value >= total.value) page.value -= 1;
    items.value = sliceItems(all);
    showEmptyState.value = originalItems.value.length === 0 && !isAddingItem.value;
    isLoading.value = false;
};
const createdComponent = (): void => {
    isLoading.value = true;
    originalItems.value = [...(props.searchConfigs?.excludedTerms ?? [])].map(String);
    renderComponent();
};
const addExcludedSearchTerms = (): void => {
    showEmptyState.value = false;
};
const onInsertTerm = (): void => {
    isAddingItem.value = true;
    searchTerm.value = '';
    page.value = 1;
    renderComponent();
    items.value.unshift({ id: 'new', value: '', originalValue: '' });
    emit('edit-change', true);
};
const onPageChange = (value: number): void => {
    page.value = value;
    isAddingItem.value = false;
    renderComponent();
};
const onLimitChange = (value: number): void => {
    limit.value = value;
    page.value = 1;
    isAddingItem.value = false;
    renderComponent();
};
const onSearchTermChange = (value: string): void => {
    page.value = 1;
    searchTerm.value = value;
    if (!(value === '' && isAddingItem.value)) {
        isAddingItem.value = false;
        renderComponent();
    }
};
const selectionChanged = (value: TermRow[] | Record<string, TermRow>): void => {
    selection.value = Array.isArray(value) ? value : Object.values(value);
};
const saveConfig = async (message: string): Promise<void> => {
    if (!props.searchConfigs) return;
    isLoading.value = true;
    Object.assign(props.searchConfigs, { excludedTerms: [...originalItems.value] });
    try {
        await repository.save(props.searchConfigs, Contena.Context.api);
        createNotificationSuccess({ message });
        isAddingItem.value = false;
        renderComponent();
        emit('edit-change', false);
    } catch (error) {
        createNotificationError({ message: error instanceof Error ? error.message : String(error) });
    } finally {
        isLoading.value = false;
    }
};
const onDeleteExcludedTerm = (terms: TermRow[]): void => {
    const values = terms.map(({ value }) => value).filter(Boolean);
    if (!values.length) {
        renderComponent();
        return;
    }
    originalItems.value = originalItems.value.filter((item) => !values.includes(item));
    void saveConfig(t('ct-settings-search.notification.deleteExcludedTermSuccess'));
};
const getOriginItem = (term: TermRow): string | null => term.originalValue || null;
const onSaveEdit = (term: TermRow): void => {
    const value = term.value.trim();
    if (!value) {
        createNotificationError({ message: t('ct-settings-search.notification.excludedTermRequired') });
        renderComponent();
        return;
    }
    const origin = getOriginItem(term);
    if (originalItems.value.some((item) => item === value && item !== origin)) {
        createNotificationError({ message: t('ct-settings-search.notification.excludedTermAlreadyExists') });
        renderComponent();
        return;
    }
    if (isAddingItem.value || !origin) {
        originalItems.value.unshift(value);
        void saveConfig(t('ct-settings-search.notification.createExcludedTermSuccess'));
        return;
    }
    originalItems.value[originalItems.value.indexOf(origin)] = value;
    void saveConfig(t('ct-settings-search.notification.updateExcludedTermSuccess'));
};
const onCancelEdit = (): void => {
    renderComponent();
    emit('edit-change', false);
};
const onBulkDeleteExcludedTerm = (): void => onDeleteExcludedTerm(selection.value);
const onResetExcludedSearchTermDefault = async (): Promise<void> => {
    try {
        await excludedSearchTermService.resetExcludedSearchTerm();
        createNotificationSuccess({ message: t('ct-settings-search.notification.resetToDefaultExcludedTermSuccess') });
        emit('data-load');
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.resetToDefaultExcludedTermError') });
    }
};
watch(() => props.searchConfigs, createdComponent, { immediate: true });

swDefinePublic({
    items,
    originalItems,
    showEmptyState,
    page,
    limit,
    total,
    searchTerm,
    isLoading,
    isAddingItem,
    selection,
    columns,
    createdComponent,
    resetData,
    addExcludedSearchTerms,
    onInsertTerm,
    renderComponent,
    filterItems,
    sliceItems,
    onPageChange,
    onLimitChange,
    onDeleteExcludedTerm,
    onSearchTermChange,
    selectionChanged,
    onSaveEdit,
    getOriginItem,
    onCancelEdit,
    onBulkDeleteExcludedTerm,
    saveConfig,
    onResetExcludedSearchTermDefault,
});

defineExpose({
    items,
    originalItems,
    showEmptyState,
    page,
    limit,
    total,
    searchTerm,
    isLoading,
    isAddingItem,
    selection,
    columns,
    createdComponent,
    resetData,
    addExcludedSearchTerms,
    onInsertTerm,
    renderComponent,
    filterItems,
    sliceItems,
    onPageChange,
    onLimitChange,
    onDeleteExcludedTerm,
    onSearchTermChange,
    selectionChanged,
    onSaveEdit,
    getOriginItem,
    onCancelEdit,
    onBulkDeleteExcludedTerm,
    saveConfig,
    onResetExcludedSearchTermDefault,
});
</script>
