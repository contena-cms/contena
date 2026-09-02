<template>
    <ct-block name="ct_settings_search_searchable_content_general">
        <mt-empty-state
            v-if="isEmpty"
            icon="regular-search"
            :headline="t('ct-settings-search.generalTab.textEmptyStateSearchableContent')"
            :description="t('ct-settings-search.generalTab.textEmptyStateSearchableContentDescription')"
        />
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
            :caption="t('ct-settings-search.generalTab.labelSearchableContent')"
        >
            <template #column-field="{ data }">
                <mt-select
                    v-if="data._isNew"
                    v-model="data.field"
                    :options="fieldConfigs"
                    value-property="value"
                    label-property="label"
                    size="small"
                    :disabled="!acl.can('blog_search_config.editor') || undefined"
                    @update:model-value="onSelectField(data)"
                />
                <span v-else>{{ getMatchingFields(data.field) }}</span>
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
                <mt-button
                    v-tooltip="t('ct-settings-search.generalTab.list.textResetRanking')"
                    class="ct-settings-search__searchable-content-list-reset"
                    variant="secondary"
                    square
                    :disabled="!acl.can('blog_search_config.editor') || undefined"
                    @click="onResetRanking(data)"
                >
                    <mt-icon name="regular-undo" size="16px" />
                </mt-button>
            </template>
        </mt-data-table>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { inject, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import { useNotification } from 'src/app/composables/use-notification';

type SearchConfig = Entity<'blog_search_config_field'>;
type FieldConfig = {
    value: string;
    label: string;
    defaultConfigs: Pick<SearchConfig, 'searchable' | 'ranking' | 'tokenize'>;
};
type Column = { property: string; label: string; renderer: 'text' | 'number'; position: number };

const props = defineProps({
    isEmpty: { type: Boolean, required: true },
    columns: { type: Array as PropType<Column[]>, required: true },
    searchConfigs: { type: Array as PropType<SearchConfig[]>, default: () => [] },
    fieldConfigs: { type: Array as PropType<FieldConfig[]>, required: true },
    isLoading: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'data-load': []; 'config-save': [] }>();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const acl = inject<AclService>('acl');
if (!acl) throw new Error('The ACL service is unavailable.');

const getMatchingFields = (fieldName: string): string =>
    props.fieldConfigs.find(({ value }) => value === fieldName)?.label ?? '';
const onSelectField = (currentField: SearchConfig): void => {
    const defaults = props.fieldConfigs.find(({ value }) => value === currentField.field)?.defaultConfigs;
    if (defaults) Object.assign(currentField, defaults);
    emit('config-save');
};
const onConfigChanged = (): void => emit('config-save');
const getConfigRankingDefault = (fieldName: string): number =>
    props.fieldConfigs.find(({ value }) => value === fieldName)?.defaultConfigs.ranking ?? 0;
const onResetRanking = (currentField: SearchConfig): void => {
    if (!currentField.field) {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
        emit('data-load');
        return;
    }
    const item = props.searchConfigs.find(({ field }) => field === currentField.field);
    if (!item) {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
        return;
    }
    item.ranking = getConfigRankingDefault(currentField.field);
    emit('config-save');
};

ctDefinePublic({
    getMatchingFields,
    onSelectField,
    onConfigChanged,
    onResetRanking,
    getConfigRankingDefault,
});

defineExpose({ getMatchingFields, onSelectField, onConfigChanged, onResetRanking, getConfigRankingDefault });
</script>
