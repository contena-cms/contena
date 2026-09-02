<template>
    <ct-block name="ct_string_filter">
        <ct-base-filter
            :title="filter.label"
            :show-reset-button="!!filter.value"
            :active="active"
            @filter-reset="resetFilter"
        >
            <ct-block name="ct_string_filter_content">
                <mt-text-field :model-value="filter.value" :placeholder="filter.placeholder" @change="updateFilter" />
            </ct-block>
        </ct-base-filter>
    </ct-block>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
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
    criteriaFilterType: {
        type: String as PropType<'contains' | 'equals' | 'equalsAny' | 'prefix' | 'suffix'>,
        required: false,
        default: 'contains',
        validValues: [
            'contains',
            'equals',
            'equalsAny',
            'prefix',
            'suffix',
        ],
        validator(value: string): boolean {
            return [
                'contains',
                'equals',
                'equalsAny',
                'prefix',
                'suffix',
            ].includes(value);
        },
    },
});
const emit = defineEmits([
    'filter-update',
    'filter-reset',
]);

const updateFilter = (newValue: string) => {
    if (!newValue || typeof props.filter.property !== 'string') {
        resetFilter();

        return;
    }

    let filterValue: string | string[] = newValue;
    let filterCriteria;

    if (props.criteriaFilterType === 'equalsAny') {
        filterValue = newValue.split(',').map((e) => e.trim());
        filterCriteria = Criteria.equalsAny(props.filter.property, filterValue);
    } else {
        filterCriteria = Criteria[props.criteriaFilterType](props.filter.property, filterValue);
    }

    emit('filter-update', props.filter.name, [filterCriteria], filterValue);
};
function resetFilter() {
    emit('filter-reset', props.filter.name);
}

ctDefinePublic({
    updateFilter,
    resetFilter,
});

defineExpose({
    updateFilter,
    resetFilter,
});
</script>
