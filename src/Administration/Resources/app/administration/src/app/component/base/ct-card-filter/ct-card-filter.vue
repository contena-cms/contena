<template>
    <ct-block name="ct_card_search_bar">
        <div class="ct-card-filter">
            <ct-block name="ct_card_filter_bar_container">
                <div :class="hasFilterClass">
                    <ct-block name="ct_card_filter_bar_container_field">
                        <ct-simple-search-field
                            v-model:value="term"
                            size="small"
                            variant="form"
                            :placeholder="placeholder"
                            :delay="delay"
                            @search-term-change="onSearchTermChange"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_card_filter_bar_container_field_filter">
                <div class="ct-card-filter-filter">
                    <slot name="filter"></slot>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-card-filter.scss';

const props = defineProps({
    placeholder: {
        type: String,
        required: false,
        default: '',
    },

    delay: {
        type: Number,
        required: false,
        default: 500,
    },

    initialSearchTerm: {
        type: String,
        required: false,
        default: '',
    },
});
const emit = defineEmits(['ct-card-filter-term-change']);

import { ref, computed, useSlots } from 'vue';

const slots = useSlots();

const term = ref('');

const hasFilter = computed(() => {
    return !!slots.filter;
});
const hasFilterClass = computed(() => {
    const classCollection = ['ct-card-filter-container'];
    if (hasFilter.value) {
        classCollection.push('hasFilter');
    }

    return classCollection.join(' ');
});

const createdComponent = () => {
    term.value = `${props.initialSearchTerm}`;
};
const onSearchTermChange = () => {
    emit('ct-card-filter-term-change', term.value);
};

createdComponent();

ctDefinePublic({
    term,
    hasFilter,
    hasFilterClass,
    createdComponent,
    onSearchTermChange,
});

defineExpose({
    term,
    hasFilter,
    hasFilterClass,
    createdComponent,
    onSearchTermChange,
});
</script>
