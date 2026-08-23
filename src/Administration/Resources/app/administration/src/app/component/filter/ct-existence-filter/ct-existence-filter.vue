<template>
    <ct-block name="sw_existence_filter">
        <div class="ct-existence-filter">
            <ct-base-filter :title="filter.label" :show-reset-button="!!value" :active="active" @filter-reset="resetFilter">
                <ct-block name="sw_existence_filter_content">
                    <mt-select
                        :model-value="value"
                        :placeholder="filter.placeholder"
                        :options="filterOptions"
                        @update:model-value="changeValue"
                    />
                </ct-block>
            </ct-base-filter>
        </div>
    </ct-block>
</template>

<script setup>
const { Criteria } = Contena.Data;

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

import { computed } from 'vue';

const value = computed(() => {
    return props.filter.value;
});
const filterOptions = computed(() => {
    return [
        {
            value: 'true',
            label: String(props.filter.optionHasCriteria),
        },
        {
            value: 'false',
            label: String(props.filter.optionNoCriteria),
        },
    ];
});

const changeValue = (newValue) => {
    if (!newValue) {
        resetFilter();
        return;
    }

    const fieldName = props.filter.property.concat(props.filter.schema ? `.${props.filter.schema.localField}` : '');

    let filterCriteria = [Criteria.equals(fieldName, null)];

    if (newValue === 'true') {
        filterCriteria = [Criteria.not('AND', filterCriteria)];
    }

    emit('filter-update', props.filter.name, filterCriteria, newValue);
};
function resetFilter() {
    emit('filter-reset', props.filter.name);
}

swDefinePublic({
    value,
    filterOptions,
    changeValue,
    resetFilter,
});

defineExpose({
    value,
    filterOptions,
    changeValue,
    resetFilter,
});
</script>
