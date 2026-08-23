<template>
    <ct-block name="sw_settings_search_searchable_content_customfields">
        <mt-empty-state
            v-if="isEmpty"
            icon="regular-search"
            :headline="t('ct-settings-search.generalTab.textEmptyStateSearchableContent')"
            :description="t('ct-settings-search.generalTab.textEmptyStateSearchableContentDescription')"
        >
            <template #button>
                <mt-button
                    size="small"
                    variant="secondary"
                    :disabled="!acl.can('blog_search_config.creator') || undefined"
                    @click="onAddField"
                >
                    {{ t('ct-settings-search.generalTab.buttonAddContent') }}
                </mt-button>
            </template>
        </mt-empty-state>
        <mt-data-table
            v-else
            class="ct-settings-search__searchable-content-list"
            :data-source="searchConfigs || []"
            :columns="columns"
            :is-loading="isLoading"
            disable-search
            disable-pagination
            :disable-edit="true"
            :disable-delete="true"
            :disable-settings-table="true"
            :caption="t('ct-settings-search.generalTab.labelCustomFieldsTab')"
        >
            <template #column-field="{ data }">
                <mt-entity-select
                    v-if="data._isNew"
                    v-model="currentCustomFieldId"
                    entity="custom_field"
                    size="small"
                    :criteria="customFieldFilteredCriteria"
                    :disabled="!acl.can('blog_search_config.editor') || undefined"
                    @item-add="onSelectCustomField"
                />
                <span v-else>{{ getMatchingCustomFields(data.field) }}</span>
            </template>
            <template #column-ranking="{ data }">
                <mt-number-field
                    v-model="data.ranking"
                    number-type="int"
                    size="small"
                    :disabled="!acl.can('blog_search_config.editor') || undefined"
                    @change="onConfigChanged"
                />
            </template>
            <template #column-searchable="{ data }">
                <mt-checkbox
                    v-model:checked="data.searchable"
                    :disabled="!acl.can('blog_search_config.editor') || undefined"
                    @change="onConfigChanged"
                />
            </template>
            <template #column-tokenize="{ data }">
                <mt-checkbox
                    v-model:checked="data.tokenize"
                    :disabled="!acl.can('blog_search_config.editor') || undefined"
                    @change="onConfigChanged"
                />
            </template>
            <template #column-actions="{ data }">
                <div class="ct-settings-search-searchable-content-customfields__actions">
                    <mt-button
                        v-tooltip="t('ct-settings-search.generalTab.list.textResetRanking')"
                        class="ct-settings-search__searchable-content-list-reset"
                        variant="secondary"
                        square
                        :disabled="!acl.can('blog_search_config.editor') || undefined"
                        @click="onResetRanking(data)"
                        ><mt-icon name="regular-undo" size="16px"
                    /></mt-button>
                    <mt-button
                        v-tooltip="t('global.default.remove')"
                        class="ct-settings-search__searchable-content-list-remove"
                        variant="critical"
                        square
                        :disabled="!acl.can('blog_search_config.deleter') || undefined"
                        @click="onRemove(data)"
                        ><mt-icon name="regular-trash" size="16px"
                    /></mt-button>
                </div>
            </template>
        </mt-data-table>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import { useNotification } from 'src/app/composables/use-notification';

import './ct-settings-search-searchable-content-customfields.scss';

type SearchConfig = Entity<'blog_search_config_field'>;
type CustomField = Entity<'custom_field'> & { config?: { label?: Record<string, string> } };
type Column = { property: string; label: string; renderer: 'text' | 'number'; position: number };

const props = defineProps({
    isEmpty: { type: Boolean, required: true },
    columns: { type: Array as PropType<Column[]>, required: true },
    searchConfigs: { type: Array as PropType<SearchConfig[]>, default: () => [] },
    isLoading: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'config-add': []; 'data-load': []; 'config-save': []; 'config-delete': [id: string] }>();
const { t } = useI18n();
const { getInlineSnippet } = useInlineSnippet();
const { createNotificationError } = useNotification();
const acl = inject<AclService>('acl');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!acl || !repositoryFactory) throw new Error('Search custom field dependencies are unavailable.');

const customFields = ref<CustomField[]>([]);
const currentCustomFieldId = ref<string | null>(null);
const customFieldRepository = repositoryFactory.create('custom_field');
const customFieldCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addAssociation('customFieldSet');
    criteria.addFilter(Contena.Data.Criteria.equals('includeInSearch', true));
    return criteria;
});
const customFieldFilteredCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addAssociation('customFieldSet');
    criteria.addFilter(Contena.Data.Criteria.equals('includeInSearch', true));
    const ids = props.searchConfigs.flatMap((item) => (item.customFieldId ? [item.customFieldId] : []));
    if (ids.length) criteria.addFilter(Contena.Data.Criteria.not('AND', [Contena.Data.Criteria.equalsAny('id', ids)]));
    return criteria;
});
const loadCustomFields = async (): Promise<void> => {
    try {
        const result = await customFieldRepository.search(customFieldCriteria.value, Contena.Context.api);
        customFields.value = [...result] as CustomField[];
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.loadError') });
    }
};
const showCustomFieldWithSet = (field: CustomField): string => {
    const set = field.customFieldSet;
    const setConfig = set?.config as { label?: Record<string, string> } | undefined;
    const snippetText = (value: unknown): string => (typeof value === 'string' ? value : '');
    const setName = set ? snippetText(getInlineSnippet(setConfig?.label ?? {})) || set.name : '';
    const itemName = snippetText(getInlineSnippet(field.config?.label ?? {})) || field.name;
    return setName ? `${setName} - ${itemName}` : itemName;
};
const getMatchingCustomFields = (field: string): string => {
    const name = field?.replace(/^customFields\./, '') ?? '';
    const item = customFields.value.find((customField) => customField.name === name);
    return item ? showCustomFieldWithSet(item) : name;
};
const onSelectCustomField = (field: CustomField): void => {
    const item = props.searchConfigs.find((config) => config._isNew);
    if (!item) return;
    item.field = `customFields.${field.name}`;
    item.customFieldId = field.id;
    currentCustomFieldId.value = field.id;
    emit('config-save');
};
const onAddField = (): void => emit('config-add');
const onConfigChanged = (): void => emit('config-save');
const onResetRanking = (field: SearchConfig): void => {
    if (!field.field) {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
        emit('data-load');
        return;
    }
    const item = props.searchConfigs.find((config) => config.field === field.field);
    if (!item) {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
        return;
    }
    item.ranking = 0;
    emit('config-save');
};
const onRemove = (field: SearchConfig): void => (field.field ? emit('config-delete', field.id) : emit('data-load'));
void loadCustomFields();

swDefinePublic({
    customFields,
    currentCustomFieldId,
    customFieldRepository,
    customFieldFilteredCriteria,
    customFieldCriteria,
    loadCustomFields,
    showCustomFieldWithSet,
    getMatchingCustomFields,
    onSelectCustomField,
    onAddField,
    onConfigChanged,
    onResetRanking,
    onRemove,
});

defineExpose({
    customFields,
    currentCustomFieldId,
    customFieldRepository,
    customFieldFilteredCriteria,
    customFieldCriteria,
    loadCustomFields,
    showCustomFieldWithSet,
    getMatchingCustomFields,
    onSelectCustomField,
    onAddField,
    onConfigChanged,
    onResetRanking,
    onRemove,
});
</script>
