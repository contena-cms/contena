<template>
    <ct-block name="ct_range_filter">
        <ct-base-filter v-bind="$attrs" class="ct-range-filter">
            <ct-block name="ct_range_filter_content">
                <ct-container :class="{ 'ct-container--has-divider': isShowDivider }">
                    <slot></slot>

                    <slot name="from-field">
                        <ct-block name="ct_range_filter_from_field"></ct-block>
                    </slot>

                    <slot name="divider">
                        <ct-block name="ct_range_filter_divider">
                            <span v-if="isShowDivider" class="ct-range-filter__divider"></span>
                        </ct-block>
                    </slot>

                    <slot name="to-field">
                        <ct-block name="ct_range_filter_to_field"></ct-block>
                    </slot>
                </ct-container>
            </ct-block>
        </ct-base-filter>
    </ct-block>
</template>

<script setup>
import './ct-range-filter.scss';
const { Criteria } = Contena.Data;

const props = defineProps({
    value: {
        type: Object,
        required: true,
    },

    property: {
        type: String,
        required: true,
    },

    isShowDivider: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits(['filter-update']);

import { inject, watch } from 'vue';

const feature = inject('feature');

const updateFilter = (range) => {
    const params = {
        ...(range.from != null ? { gte: range.from } : {}),
        ...(range.to != null ? { lte: range.to } : {}),
    };

    const filterCriteria = [Criteria.range(props.property, params)];
    emit('filter-update', filterCriteria);
};

watch(
    () => props.value,
    (newValue) => {
        updateFilter(newValue);
    },
    { deep: true },
);

ctDefinePublic({
    feature,
    updateFilter,
});

defineExpose({
    feature,
    updateFilter,
});
</script>
