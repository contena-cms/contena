<template>
    <ct-block name="ct_settings_search_view_live_search">
        <div class="ct-settings-search__view-live-search">
            <ct-settings-search-search-index
                v-if="!frontendEsEnable"
                :is-loading="isLoading"
                @edit-change="$emit('edit-change', $event)"
            />
            <ct-settings-search-live-search
                v-bind="$props"
                @channel-change="$emit('channel-change', $event)"
                @live-search-results-change="$emit('live-search-results-change', $event)"
            />
        </div>
    </ct-block>
</template>
<script setup lang="ts">
import { computed, type PropType } from 'vue';

defineProps({
    currentChannelId: { type: String, default: null },
    searchTerms: { type: String, default: '' },
    searchResults: { type: Object as PropType<Record<string, unknown> | null>, default: null },
    isLoading: { type: Boolean, default: false },
});
defineEmits<{
    'edit-change': [editing: boolean];
    'channel-change': [id: string | null];
    'live-search-results-change': [result: { searchTerms: string; searchResults: unknown }];
}>();
const frontendEsEnable = computed(() => Contena.Context.app.storefrontEsEnable ?? false);

ctDefinePublic({
    frontendEsEnable,
});

defineExpose({ frontendEsEnable });
</script>
