<template>
    <ct-block name="sw_settings_search_view_general">
        <div class="ct-settings-search__view-general">
            <ct-settings-search-search-behaviour
                :is-loading="isLoading"
                :search-behaviour-configs="blogSearchConfigs || undefined"
            />
            <ct-settings-search-searchable-content
                :blog-search-configs="blogSearchConfigs"
                :search-config-id="searchConfigId"
                @edit-change="$emit('edit-change', $event)"
            />
            <ct-settings-search-excluded-search-terms
                :search-configs="blogSearchConfigs"
                :is-excluded-terms-loading="isLoading"
                @edit-change="$emit('edit-change', $event)"
                @data-load="loadData"
            />
        </div>
    </ct-block>
</template>
<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, type PropType } from 'vue';

const props = defineProps({
    blogSearchConfigs: { type: Object as PropType<Entity<'blog_search_config'> | null>, default: null },
    isLoading: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'excluded-search-terms-load': []; 'edit-change': [editing: boolean] }>();
const searchConfigId = computed(() => props.blogSearchConfigs?.id ?? '');
const loadData = (): void => emit('excluded-search-terms-load');

swDefinePublic({
    searchConfigId,
    loadData,
});

defineExpose({ searchConfigId, loadData });
</script>
