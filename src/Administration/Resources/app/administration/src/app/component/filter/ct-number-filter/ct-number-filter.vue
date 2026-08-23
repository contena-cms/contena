<template>
    <ct-block name="sw_number_filter">
        <ct-range-filter
            class="ct-number-filter"
            :title="filter.label"
            :filter="filter"
            :active="active"
            :value="numberValue"
            :property="filter.property"
            :show-reset-button="!!numberValue.from || !!numberValue.to"
            @filter-update="updateFilter"
            @filter-reset="resetFilter"
        >
            <template #from-field>
                <ct-block name="sw_number_filter_from_field">
                    <mt-number-field
                        v-model="numberValue.from"
                        v-bind="$attrs"
                        class="ct-number-filter__from"
                        :label="fromToFieldLabel('from')"
                        :placeholder="filter.fromPlaceholder"
                    />
                </ct-block>
            </template>

            <template #to-field>
                <ct-block name="sw_number_filter_to_field">
                    <mt-number-field
                        v-model="numberValue.to"
                        v-bind="$attrs"
                        class="ct-number-filter__to"
                        :label="fromToFieldLabel('to')"
                        :placeholder="filter.toPlaceholder"
                    />
                </ct-block>
            </template>
        </ct-range-filter>
    </ct-block>
</template>

<script setup>
import './ct-number-filter.scss';

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
    'filter-reset',
    'filter-update',
]);

import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const numberValue = ref({
    from: null,
    to: null,
});

const fromToFieldLabel = (type) => {
    const key = `${type}FieldLabel`;

    if (!props.filter.hasOwnProperty(key)) {
        return t(`global.default.${type}`);
    }

    const label = props.filter[key];

    if (!label) {
        return null;
    }

    return label;
};
const updateFilter = (params) => {
    if (numberValue.value.from == null && numberValue.value.to == null) {
        emit('filter-reset', props.filter.name);
        return;
    }

    const { value } = props.filter;
    if (value && value.from === numberValue.value.from && value.to === numberValue.value.to) {
        return;
    }

    emit('filter-update', props.filter.name, params, numberValue.value);
};
const resetFilter = () => {
    numberValue.value = { from: null, to: null };
    emit('filter-reset', props.filter.name, numberValue.value);
};

watch(
    () => props.filter.value,
    () => {
        if (props.filter.value) {
            numberValue.value = { ...props.filter.value };
        }
    },
);

swDefinePublic({
    numberValue,
    fromToFieldLabel,
    updateFilter,
    resetFilter,
});

defineExpose({
    numberValue,
    fromToFieldLabel,
    updateFilter,
    resetFilter,
});
</script>
