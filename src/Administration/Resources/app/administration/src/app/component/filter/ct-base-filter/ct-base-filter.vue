<template>
    <ct-block name="ct_base_filter">
        <div class="ct-base-filter">
            <ct-block name="ct_base_filter_headline">
                <div class="ct-base-filter__headline">
                    <h4 class="ct-base-filter__title">
                        {{ title }}
                    </h4>
                    <a
                        v-if="showResetButton"
                        class="ct-base-filter__reset"
                        role="button"
                        tabindex="0"
                        @click="resetFilter"
                        @keydown.enter="resetFilter"
                        >{{ $t('ct-base-filter.resetButton') }}</a
                    >
                </div>
            </ct-block>

            <ct-block name="ct_base_filter_content">
                <slot></slot>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-base-filter.scss';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    showResetButton: {
        type: Boolean,
        required: true,
    },
    active: {
        type: Boolean,
        required: true,
    },
});
const emit = defineEmits(['filter-reset']);

import { watch } from 'vue';

const resetFilter = () => {
    emit('filter-reset');
};

watch(
    () => props.active,
    (value) => {
        if (!value) {
            resetFilter();
        }
    },
);

ctDefinePublic({
    resetFilter,
});

defineExpose({
    resetFilter,
});
</script>
