<template>
    <ct-block name="ct_multi_select_filter">
        <ct-base-filter
            :title="filter.label"
            :show-reset-button="!!values.length"
            :active="active"
            @filter-reset="resetFilter"
        >
            <ct-block name="ct_multi_select_filter_content">
                <ct-block name="ct_multi_select_filter_content_entity_select">
                    <ct-entity-multi-select
                        v-if="isEntityMultiSelect"
                        :label-property="labelProperty"
                        :placeholder="filter.placeholder"
                        :criteria="filter.criteria"
                        :entity-name="filter.schema.entity"
                        :entity-collection="values"
                        :display-variants="filter.displayVariants"
                        @update:entity-collection="changeValue"
                    >
                        <template #selection-label-property="{ item, index }">
                            <ct-block name="ct_multi_select_filter_content_slot_selection_label_property">
                                <slot name="selection-label-property" v-bind="{ item, index }"></slot>
                            </ct-block>
                        </template>

                        <template #result-item="{ item, index }">
                            <ct-block name="ct_multi_select_filter_content_slot_result_item">
                                <slot name="result-item" v-bind="{ item, index }"></slot>
                            </ct-block>
                        </template>
                    </ct-entity-multi-select>
                </ct-block>

                <ct-block name="ct_multi_select_filter_content_option_select">
                    <mt-select
                        v-if="filter.options"
                        :label-property="filter.labelProperty"
                        :value-property="filter.valueProperty"
                        :placeholder="filter.placeholder"
                        :options="filter.options"
                        :model-value="values"
                        enable-multi-selection
                        @update:model-value="changeValue"
                    />
                </ct-block>
            </ct-block>
        </ct-base-filter>
    </ct-block>
</template>

<script setup>
const { Criteria, EntityCollection } = Contena.Data;

const props = defineProps({
    filter: {
        type: Object,
        required: true,
    },
    active: {
        type: Boolean,
        required: true,
    },
});
const emit = defineEmits([
    'filter-update',
    'filter-reset',
]);

import { computed, inject } from 'vue';

const repositoryFactory = inject('repositoryFactory');

const isEntityMultiSelect = computed(() => {
    return !props.filter.options;
});
const labelProperty = computed(() => {
    return props.filter.labelProperty || 'name';
});
const values = computed(() => {
    if (!isEntityMultiSelect.value) {
        return props.filter.value || [];
    }

    const entities = new EntityCollection('', props.filter.schema.entity, Contena.Context.api);

    if (Array.isArray(props.filter.value)) {
        props.filter.value.forEach((value) => {
            const entityValue = {
                id: value.id,
                [labelProperty.value]: value[labelProperty.value],
            };

            if (props.filter.displayVariants) {
                entityValue.variation = value.variation;
            }

            entities.push(entityValue);
        });
    }

    return entities;
});

const changeValue = (newValues) => {
    if (newValues.length <= 0) {
        resetFilter();
        return;
    }

    let filterCriteria = [];
    if (props.filter.existingType) {
        const multiFilter = [];
        newValues.forEach((value) => {
            multiFilter.push(
                Criteria.not('and', [
                    Criteria.equals(`${value}.id`, null),
                ]),
            );
        });
        filterCriteria.push(Criteria.multi('or', multiFilter));
    } else {
        filterCriteria = [
            props.filter.schema
                ? Criteria.equalsAny(
                      `${props.filter.property}.${props.filter.schema.referenceField}`,
                      newValues.map((newValue) => newValue[props.filter.schema.referenceField]),
                  )
                : Criteria.equalsAny(props.filter.property, newValues),
        ];
    }

    const values = !isEntityMultiSelect.value
        ? newValues
        : newValues.map((value) => {
              if (!props.filter.displayVariants) {
                  return {
                      id: value.id,
                      [labelProperty.value]: value?.translated?.[labelProperty.value] || value?.[labelProperty.value],
                  };
              }

              return {
                  id: value.id,
                  variation: value.variation,
                  [labelProperty.value]: value?.translated?.[labelProperty.value] || value?.[labelProperty.value],
              };
          });

    emit('filter-update', props.filter.name, filterCriteria, values);
};
function resetFilter() {
    emit('filter-reset', props.filter.name);
}

ctDefinePublic({
    repositoryFactory,
    isEntityMultiSelect,
    labelProperty,
    values,
    changeValue,
    resetFilter,
});

defineExpose({
    repositoryFactory,
    isEntityMultiSelect,
    labelProperty,
    values,
    changeValue,
    resetFilter,
});
</script>
