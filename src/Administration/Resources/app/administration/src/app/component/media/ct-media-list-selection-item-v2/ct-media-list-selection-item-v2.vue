<template>
    <ct-block name="ct_media_list_selection_item_v2">
        <div class="ct-media-list-selection-item-v2" :class="itemClasses">
            <ct-block name="ct_media_list_selection_item_v2_preview">
                <template v-if="!isPlaceholder">
                    <ct-block name="ct_media_list_selection_item_v2_preview_media">
                        <ct-media-preview-v2
                            class="ct-media-list-selection-item-v2__image"
                            :source="sourceId"
                            :hide-tooltip="hideTooltip"
                            @click="$emit('click')"
                        />
                    </ct-block>

                    <ct-block name="ct_media_list_selection_item_v2_preview_actions">
                        <ct-context-button
                            v-if="!disabled && !hideActions"
                            class="ct-media-list-selection-item-v2__context-button"
                        >
                            <ct-block name="ct_media_list_selection_item_v2_context">
                                <ct-block name="ct_media_list_selection_item_v2_context_delete_action">
                                    <ct-context-menu-item variant="danger" @click="$emit('item-remove')">
                                        {{ $t('global.default.remove') }}
                                    </ct-context-menu-item>
                                </ct-block>
                            </ct-block>
                        </ct-context-button>
                    </ct-block>
                </template>
            </ct-block>

            <ct-block name="ct_media_list_selection_item_v2_placeholder">
                <template v-if="!isPlaceholder"><!-- Keeps the conditional chain connected across ct-block. --></template>
                <template v-else>
                    <ct-block name="ct_media_list_selection_item_v2_placeholder_icon">
                        <mt-icon
                            class="ct-media-list-selection-item-v2__placeholder-icon"
                            name="regular-image"
                            size="16px"
                        />
                    </ct-block>
                </template>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-list-selection-item-v2.scss';

const props = defineProps({
    item: {
        required: true,
    },

    hideActions: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideTooltip: {
        type: Boolean,
        required: false,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
defineEmits([
    'click',
    'item-remove',
]);

import { computed } from 'vue';

const isPlaceholder = computed(() => {
    return !!props.item.isPlaceholder;
});
const itemClasses = computed(() => {
    return {
        'is--placeholder': isPlaceholder.value,
    };
});
const sourceId = computed(() => {
    return props.item.mediaId || props.item.targetId || props.item.id;
});

ctDefinePublic({
    isPlaceholder,
    itemClasses,
    sourceId,
});

defineExpose({
    isPlaceholder,
    itemClasses,
    sourceId,
});
</script>
