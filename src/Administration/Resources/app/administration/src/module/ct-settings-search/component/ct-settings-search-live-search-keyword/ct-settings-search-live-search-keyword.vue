<template>
    <ct-block name="ct_settings_search_view_live_search_keyword">
        <span class="ct-settings-search-live-search-keyword">
            <!-- The upstream Advanced Search extension supplies already highlighted markup. -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <span v-if="textIsHighlighted" v-html="text" />
            <span v-for="(keyword, index) in parsedMessage" v-else :key="`${index}-${keyword}`" :class="getClass(index)">
                {{ keyword }}
            </span>
        </span>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import './ct-settings-search-live-search-keyword.scss';

const props = withDefaults(
    defineProps<{
        text: string;
        searchTerm: string;
        highlightClass?: string;
    }>(),
    {
        highlightClass: 'ct-settings-search-live-search-keyword__highlight',
    },
);

const textIsHighlighted = computed(() => props.text.includes(props.highlightClass));
const parsedSearch = computed(() => {
    const term = props.searchTerm.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return `(${term.replace(/ +/g, '|')})`;
});
const parsedMessage = computed(() => {
    if (textIsHighlighted.value || !props.searchTerm.trim()) {
        return [props.text];
    }

    return props.text.split(new RegExp(parsedSearch.value, 'gi'));
});
const getClass = (messageIndex: number) => {
    if (textIsHighlighted.value) {
        return undefined;
    }

    return messageIndex % 2 === 1 ? props.highlightClass : undefined;
};

ctDefinePublic({
    textIsHighlighted,
    parsedSearch,
    parsedMessage,
    getClass,
});

defineExpose({ textIsHighlighted, parsedSearch, parsedMessage, getClass });
</script>
