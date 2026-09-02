<template>
    <ct-block name="ct_media_base_item">
        <div
            class="ct-media-base-item"
            :class="mediaItemClasses"
            role="button"
            tabindex="0"
            @click="handleItemClick"
            @keydown.enter.self="handleItemClick"
        >
            <ct-block name="ct_media_base_item_selected_indicator">
                <mt-checkbox
                    v-if="!isList && allowEdit"
                    v-model:checked="listSelected"
                    class="ct-media-base-item__selected-indicator"
                    :class="selectionIndicatorClasses"
                    @update:checked="onClickedItem"
                />
            </ct-block>

            <ct-block name="ct_media_base_item_preview">
                <div class="ct-media-base-item__preview-container">
                    <slot name="preview" v-bind="{ item }">
                        <ct-block name="ct_media_base_item_slot_media_preview"></ct-block>
                    </slot>

                    <ct-block name="ct_media_base_spatial_label_indicator">
                        <div v-if="isSpatial" class="ct-media-base-item__labels">
                            <ct-label variant="neutral-reversed" appearance="pill" size="medium">
                                <mt-icon
                                    v-if="item.config?.spatial?.arReady ?? defaultArReady"
                                    name="regular-AR"
                                    size="16px"
                                />
                                <mt-icon v-else name="regular-3d" size="16px" />

                                <span class="ct-media-base-item__labels-text">{{
                                    (item.config?.spatial?.arReady ?? defaultArReady)
                                        ? $t('ct-media.general.arReady')
                                        : $t('ct-media.general.spatialMedia')
                                }}</span>
                            </ct-label>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_media_base_item_name_container">
                <div class="ct-media-base-item__name-container" :class="mediaNameContainerClasses">
                    <slot name="name" v-bind="{ item, isInlineEdit, startInlineEdit, endInlineEdit }"></slot>
                </div>
            </ct-block>

            <ct-block name="ct_media_base_item_metadata_container">
                <div
                    v-if="(isList || showGridMetadata) && showContextMenuButton"
                    class="ct-media-base-item__metadata-container"
                >
                    <ct-block name="ct_media_base_item_metadata">
                        <slot name="metadata" v-bind="{ item }">
                            <ct-block name="ct_media_base_item_slot_media_item_metadata"></ct-block>
                        </slot>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_media_base_item_context_menu">
                <ct-context-button v-if="showContextMenuButton && !isLoading" ref="ctContextButton">
                    <ct-block name="ct_media_base_item_context_items">
                        <slot name="context-menu" v-bind="{ item, startInlineEdit, allowEdit, allowDelete }">
                            <ct-block name="ct_media_base_item_slot_media_item_context_menu"></ct-block>
                        </slot>
                    </ct-block>
                </ct-context-button>
            </ct-block>

            <ct-block name="ct_media_base_item_list_selected_indicator">
                <mt-checkbox
                    v-if="isList && showSelectionIndicator && allowMultiSelect"
                    v-model:checked="listSelected"
                    class="ct-media-base-item__selected-indicator"
                    :class="selectionIndicatorClasses"
                    @update:checked="onClickedItem"
                />
            </ct-block>

            <ct-block name="ct_media_base_item_loading_indicator">
                <mt-icon v-if="isLoading" class="ct-media-base-item__loader" name="regular-spinner-star" size="16px" />
            </ct-block>

            <slot name="modal-windows" v-bind="{ item, allowEdit, allowDelete }"></slot>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-base-item.scss';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },

    isList: {
        type: Boolean,
        required: false,
        default: false,
    },

    showSelectionIndicator: {
        required: false,
        type: Boolean,
        default: false,
    },

    showContextMenuButton: {
        type: Boolean,
        required: false,
        default: true,
    },

    showGridMetadata: {
        type: Boolean,
        required: false,
        default: false,
    },

    selected: {
        type: Boolean,
        required: false,
        default: false,
    },

    editable: {
        type: Boolean,
        required: false,
        default: true,
    },

    allowMultiSelect: {
        type: Boolean,
        required: false,
        default: true,
    },

    truncateRight: {
        type: Boolean,
        required: false,
        default: false,
    },

    allowEdit: {
        type: Boolean,
        required: false,
        default: true,
    },

    allowDelete: {
        type: Boolean,
        required: false,
        default: true,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-item-click',
    'media-item-selection-add',
    'media-item-selection-remove',
]);

import { ref, computed, inject } from 'vue';

const systemConfigApiService = inject('systemConfigApiService');

const isInlineEdit = ref(false);
const defaultArReady = ref(false);

const mediaItemClasses = computed(() => {
    return {
        'is--list': props.isList,
        'is--selected': props.selected || isInlineEdit.value,
    };
});
const mediaNameContainerClasses = computed(() => {
    return {
        'is--truncate-right': props.truncateRight,
    };
});
const listSelected = computed(() => {
    return props.selected && props.showSelectionIndicator;
});
const selectionIndicatorClasses = computed(() => {
    return {
        'selected-indicator--visible': props.showSelectionIndicator,
        'selected-indicator--list': props.isList,
        'selected-indicator--checked': listSelected.value,
        'selected-indicator--is-allowed': props.allowMultiSelect,
    };
});
const isLoading = computed(() => {
    return props.item.isLoading;
});
const isSpatial = computed(() => {
    // we need to check the media url since media.fileExtension is set directly after upload
    return props.item.fileExtension === 'glb' || !!props.item?.url?.endsWith('.glb');
});

const createdComponent = () => {
    systemConfigApiService.getValues('core.media').then((values) => {
        defaultArReady.value = values['core.media.defaultEnableAugmentedReality'];
    });
};
const handleItemClick = (originalDomEvent) => {
    if (props.disabled) {
        return;
    }

    if (isSelectionIndicatorClicked(originalDomEvent.composedPath())) {
        return;
    }
    emit('media-item-click', {
        originalDomEvent,
        item: props.item,
    });
};
function isSelectionIndicatorClicked(path) {
    return path.some((parent) => {
        return (
            parent.classList &&
            (parent.classList.contains('ct-media-base-item__selected-indicator') ||
                parent.classList.contains('ct-context-button'))
        );
    });
}
const onClickedItem = (originalDomEvent) => {
    if (!listSelected.value || !props.allowMultiSelect) {
        selectItem(originalDomEvent);
        return;
    }
    removeFromSelection(originalDomEvent);
};
function selectItem(originalDomEvent) {
    emit('media-item-selection-add', {
        originalDomEvent,
        item: props.item,
    });
}
function removeFromSelection(originalDomEvent) {
    emit('media-item-selection-remove', {
        originalDomEvent,
        item: props.item,
    });
}
const startInlineEdit = () => {
    if (props.editable && props.allowEdit) {
        isInlineEdit.value = true;
    }
};
const endInlineEdit = () => {
    isInlineEdit.value = false;
};

createdComponent();

ctDefinePublic({
    systemConfigApiService,
    isInlineEdit,
    defaultArReady,
    mediaItemClasses,
    mediaNameContainerClasses,
    listSelected,
    selectionIndicatorClasses,
    isLoading,
    isSpatial,
    createdComponent,
    handleItemClick,
    isSelectionIndicatorClicked,
    onClickedItem,
    selectItem,
    removeFromSelection,
    startInlineEdit,
    endInlineEdit,
});

defineExpose({
    systemConfigApiService,
    isInlineEdit,
    defaultArReady,
    mediaItemClasses,
    mediaNameContainerClasses,
    listSelected,
    selectionIndicatorClasses,
    isLoading,
    isSpatial,
    createdComponent,
    handleItemClick,
    isSelectionIndicatorClicked,
    onClickedItem,
    selectItem,
    removeFromSelection,
    startInlineEdit,
    endInlineEdit,
});
</script>
