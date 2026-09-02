<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="ct_settings_listing_option_criteria_card">
        <mt-card
            class="ct-settings-listing-option-criteria-grid"
            :title="$t('ct-settings-listing.base.criteria.title')"
            position-identifier="ct-settings-listing-option-criteria-grid"
        >
            <template #toolbar>
                <ct-block name="ct_settings_listing_option_criteria_card_toolbar_select">
                    <mt-select
                        class="ct-settings-listing-option-criteria-grid__criteria-select"
                        :model-value="selectedCriteria"
                        :options="availableCriteriaOptions"
                        :placeholder="$t('ct-settings-listing.base.criteria.selectPlaceholder')"
                        :disabled="blogSortingEntity.locked || undefined"
                        @update:model-value="onAddCriteria"
                    />
                </ct-block>
            </template>

            <ct-block name="ct_settings_listing_option_criteria_card_grid">
                <mt-data-table
                    v-if="sortingFields.length > 0"
                    :data-source="sortingFields"
                    :columns="columns"
                    :disable-edit="true"
                    :disable-delete="blogSortingEntity.locked"
                    :disable-settings-table="true"
                    disable-search
                    disable-pagination
                    @item-delete="onRemoveCriteria"
                >
                    <template #column-field="{ data }">
                        <mt-select
                            v-if="isCustomField(data.field) || data.field === 'customField'"
                            :model-value="data.field === 'customField' ? null : data.field"
                            :options="customFieldOptions"
                            :disabled="blogSortingEntity.locked || undefined"
                            :placeholder="$t('global.ct-single-select.valuePlaceholder')"
                            @update:model-value="(value) => changeCustomField(data.index, value)"
                        />
                        <span v-else>{{ getCriteriaLabel(data.field) }}</span>
                    </template>

                    <template #column-order="{ data }">
                        <mt-select
                            v-model="blogSortingEntity.fields[data.index].order"
                            :options="orderOptions"
                            :disabled="blogSortingEntity.locked || undefined"
                        />
                    </template>

                    <template #column-priority="{ data }">
                        <mt-number-field
                            v-model="blogSortingEntity.fields[data.index].priority"
                            number-type="int"
                            :min="0"
                            :disabled="blogSortingEntity.locked || undefined"
                        />
                    </template>
                </mt-data-table>

                <mt-empty-state
                    v-else
                    icon="regular-sort"
                    :headline="$t('ct-settings-listing.base.criteria.title')"
                    :description="$t('ct-settings-listing.base.criteria.emptyStateSubline')"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import { useNotification } from 'src/app/composables/use-notification';

interface BlogSortingField {
    field: string;
    order: 'asc' | 'desc';
    priority: number;
    naturalSorting: boolean | number | null;
}

interface BlogSortingOption {
    id: string;
    locked: boolean;
    fields: BlogSortingField[];
}

interface CustomFieldRow {
    id: string;
    name: string;
    type: string;
    customFieldSetId: string;
    config: { label?: Record<string, string> };
}

interface CustomFieldSetRelationRow {
    customFieldSetId: string;
}

interface SortingFieldRow extends BlogSortingField {
    id: string;
    index: number;
}

interface TableColumn {
    property: string;
    label: string;
    renderer: 'text' | 'number';
    position: number;
    sortable: boolean;
    allowResize: boolean;
}

const { Criteria } = Contena.Data;
const props = defineProps({
    blogSortingEntity: { type: Object as PropType<BlogSortingOption>, required: true },
});
const emit = defineEmits<{
    changed: [];
    'delete-requested': [index: number];
}>();
const { t } = useI18n();
const { getInlineSnippet } = useInlineSnippet();
const { createNotificationError } = useNotification();
const snippetText = (value: unknown): string => (typeof value === 'string' ? value : '');

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) {
    throw new Error('The repository factory is unavailable.');
}

const customFieldRepository = repositoryFactory.create('custom_field');
const customFieldSetRelationRepository = repositoryFactory.create('custom_field_set_relation');
const selectedCriteria = ref<string | null>(null);
const customFields = ref<CustomFieldRow[]>([]);
const customFieldSetIds = ref<string[] | null>(null);

const sortingFields = computed<SortingFieldRow[]>(() =>
    props.blogSortingEntity.fields
        .map((field, index) => ({ ...field, id: `${index}-${field.field}`, index }))
        .sort((first, second) => second.priority - first.priority),
);
const columns = computed<TableColumn[]>(() => [
    {
        property: 'field',
        label: t('ct-settings-listing.general.blogSortingCriteriaGrid.header.name'),
        renderer: 'text',
        position: 100,
        sortable: false,
        allowResize: true,
    },
    {
        property: 'order',
        label: t('ct-settings-listing.general.blogSortingCriteriaGrid.header.order'),
        renderer: 'text',
        position: 200,
        sortable: false,
        allowResize: true,
    },
    {
        property: 'priority',
        label: t('ct-settings-listing.general.blogSortingCriteriaGrid.header.priority'),
        renderer: 'number',
        position: 300,
        sortable: false,
        allowResize: true,
    },
]);
const criteriaOptions = computed(() =>
    [
        'blog.name',
        'blog.releaseDate',
        'blog.createdAt',
        '_score',
        'customField',
    ]
        .map((value) => ({ value, label: getCriteriaLabel(value) }))
        .sort((first, second) => first.label.localeCompare(second.label)),
);
const availableCriteriaOptions = computed(() =>
    criteriaOptions.value.filter(
        (option) =>
            option.value === 'customField' || !props.blogSortingEntity.fields.some((field) => field.field === option.value),
    ),
);
const customFieldOptions = computed(() =>
    customFields.value
        .filter(
            (customField) =>
                !props.blogSortingEntity.fields.some((field) => field.field === `customFields.${customField.name}`),
        )
        .map((customField) => ({
            value: `customFields.${customField.name}`,
            label: snippetText(getInlineSnippet(customField.config.label ?? {})) || customField.name,
        })),
);
const orderOptions = computed(() => [
    { value: 'asc', label: t('global.default.ascending') },
    { value: 'desc', label: t('global.default.descending') },
]);

const fetchCustomFieldSetIds = async (): Promise<void> => {
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equals('entityName', 'blog'));
    const result = await customFieldSetRelationRepository.search(criteria, Contena.Context.api);
    customFieldSetIds.value = [...result].map(
        (relation) => (relation as unknown as CustomFieldSetRelationRow).customFieldSetId,
    );
};
const fetchCustomFields = async (): Promise<void> => {
    const criteria = new Criteria(1, 100);
    criteria.addFilter(
        Criteria.not('and', [
            Criteria.equalsAny('type', [
                'price',
                'json',
                'text',
                'html',
            ]),
        ]),
    );
    if (customFieldSetIds.value?.length) {
        criteria.addFilter(Criteria.equalsAny('customFieldSetId', customFieldSetIds.value));
    } else {
        criteria.addFilter(Criteria.equals('id', null));
    }

    const result = await customFieldRepository.search(criteria, Contena.Context.api);
    customFields.value = [...result] as unknown as CustomFieldRow[];
};
const onAddCriteria = (field: string | null): void => {
    selectedCriteria.value = null;
    if (!field) return;

    if (field !== 'customField' && props.blogSortingEntity.fields.some((item) => item.field === field)) {
        createNotificationError({
            message: t('ct-settings-listing.general.blogSortingCriteriaGrid.options.criteriaAlreadyUsed', {
                criteriaName: getCriteriaLabel(field),
            }),
        });
        return;
    }

    props.blogSortingEntity.fields.push({
        field,
        order: 'asc',
        priority: 1,
        naturalSorting: 0,
    });
    emit('changed');
};
const onRemoveCriteria = (field: SortingFieldRow): void => {
    emit('delete-requested', field.index);
};
const changeCustomField = (index: number, value: string | null): void => {
    props.blogSortingEntity.fields[index].field = value ?? 'customField';
    emit('changed');
};
const isCustomField = (field: string): boolean => field.startsWith('customFields.');
const getCriteriaLabel = (field: string): string => {
    if (isCustomField(field)) {
        const name = field.replace(/^customFields\./, '');
        const customField = customFields.value.find((entry) => entry.name === name);
        return snippetText(getInlineSnippet(customField?.config.label ?? {})) || name;
    }

    return t(`ct-settings-listing.general.blogSortingCriteriaGrid.options.label.${field}`);
};

void fetchCustomFieldSetIds().then(fetchCustomFields);

ctDefinePublic({
    selectedCriteria,
    customFields,
    customFieldSetIds,
    sortingFields,
    columns,
    criteriaOptions,
    availableCriteriaOptions,
    customFieldOptions,
    orderOptions,
    fetchCustomFieldSetIds,
    fetchCustomFields,
    onAddCriteria,
    onRemoveCriteria,
    changeCustomField,
    isCustomField,
    getCriteriaLabel,
});

defineExpose({
    selectedCriteria,
    customFields,
    customFieldSetIds,
    sortingFields,
    columns,
    criteriaOptions,
    availableCriteriaOptions,
    customFieldOptions,
    orderOptions,
    fetchCustomFieldSetIds,
    fetchCustomFields,
    onAddCriteria,
    onRemoveCriteria,
    changeCustomField,
    isCustomField,
    getCriteriaLabel,
});
</script>

<style scoped>
.ct-settings-listing-option-criteria-grid__criteria-select {
    width: min(420px, 100%);
}
</style>
