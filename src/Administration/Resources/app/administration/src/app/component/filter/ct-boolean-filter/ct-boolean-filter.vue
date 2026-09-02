<template>
    <ct-block name="ct_boolean_filter">
        <div class="ct-boolean-filter">
            <ct-base-filter :title="filter.label" :show-reset-button="!!value" :active="active" @filter-reset="resetFilter">
                <ct-block name="ct_boolean_filter_content">
                    <mt-select
                        :model-value="value"
                        :placeholder="filter.placeholder"
                        :options="options"
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
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const value = computed(() => {
    return props.filter.value;
});
const options = computed(() => {
    return [
        {
            id: 1,
            label: t('ct-boolean-filter.active'),
            value: 'true',
        },
        {
            id: 2,
            label: t('ct-boolean-filter.inactive'),
            value: 'false',
        },
    ];
});

const changeValue = (newValue) => {
    if (!newValue) {
        resetFilter();
        return;
    }

    const filterCriteria = [
        Criteria.equals(props.filter.property, newValue === 'true'),
    ];

    emit('filter-update', props.filter.name, filterCriteria, newValue);
};
function resetFilter() {
    emit('filter-reset', props.filter.name);
}

ctDefinePublic({
    value,
    options,
    changeValue,
    resetFilter,
});

defineExpose({
    value,
    options,
    changeValue,
    resetFilter,
});
</script>
