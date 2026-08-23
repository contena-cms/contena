<template>
    <ct-block name="sw_media_quickinfo_multiple">
        <div class="ct-media-quickinfo-multiple">
            <ct-block name="sw_media_quickinfo_multiple_quickactions">
                <ct-media-collapse
                    v-if="editable"
                    :title="translate('ct-media.sidebar.sections.actions')"
                    :expand-on-loading="true"
                >
                    <template #content>
                        <ct-block name="sw_media_quickinfo_multiple_quickactions_content">
                            <ul class="ct-media-sidebar__quickactions-list">
                                <ct-block name="sw_media_quickinfo_multiple_quickactions_move">
                                    <li
                                        class="quickaction--move"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalMove"
                                        @keydown.enter="openModalMove"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-file-export"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.move') }}
                                    </li>
                                </ct-block>

                                <ct-block name="sw_media_quickinfo_multiple_quickactions_delete">
                                    <li
                                        v-if="!isPrivate"
                                        class="is--danger"
                                        :class="quickActionClasses(!acl.can('media.deleter'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalDelete"
                                        @keydown.enter="openModalDelete"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-trash"
                                            class="ct-media-sidebar__quickactions-icon is--danger"
                                        />
                                        {{ translate('ct-media.sidebar.actions.delete') }}
                                    </li>
                                </ct-block>

                                <ct-block name="sw_media_quickinfo_folder_quickactions_dissolve">
                                    <li
                                        v-if="!hasMedia"
                                        class="quickaction--dissolve"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openFolderDissolve"
                                        @keydown.enter="openFolderDissolve"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-spinner-star"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.dissolve') }}
                                    </li>
                                </ct-block>
                            </ul>
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="sw_media_quickinfo_multiple_file_names">
                <ct-media-collapse :expand-on-loading="true" :title="translate('ct-media.sidebar.sections.selectedFiles')">
                    <template #content>
                        <ct-block name="sw_media_quickinfo_multiple_file_names_content">
                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                            <label class="ct-media-quickinfo-multiple__second-headline">{{ getFileSizeLabel }}</label>
                            <ct-media-entity-mapper
                                v-for="mediaItem in items"
                                :key="mediaItem.id"
                                :item="mediaItem"
                                :selected="true"
                                :is-list="true"
                                :show-context-menu-button="false"
                                :show-selection-indicator="true"
                                @media-item-selection-remove="onRemoveItemFromSelection"
                            />
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="sw_media_sidebar_modal_delete">
                <ct-media-modal-delete
                    v-if="showModalDelete"
                    :items-to-delete="items"
                    @media-delete-modal-close="closeModalDelete"
                    @media-delete-modal-items-delete="deleteSelectedItems"
                />
            </ct-block>

            <ct-block name="sw_media_sidebar_folder_dissolve_modal">
                <ct-media-modal-folder-dissolve
                    v-if="!hasMedia && showFolderDissolve"
                    :items-to-dissolve="items"
                    @media-folder-dissolve-modal-dissolve="onFolderDissolved"
                    @media-folder-dissolve-modal-close="closeFolderDissolve"
                />
            </ct-block>

            <ct-block name="sw_media_sidebar_folder_move_modal">
                <ct-media-modal-move
                    v-if="showModalMove"
                    :items-to-move="items"
                    @media-move-modal-close="closeModalMove"
                    @media-move-modal-items-move="onFolderMoved"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-quickinfo-multiple.scss';

const props = defineProps({
    items: {
        required: true,
        type: Array,
    },

    editable: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-item-selection-remove',
    'media-sidebar-items-delete',
    'media-sidebar-folder-items-dissolve',
    'media-sidebar-items-move',
]);

import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import useMediaSidebarModal from 'src/app/composables/use-media-sidebar-modal';

const { t } = useI18n();

const translate = t;
const acl = inject('acl');
const itemsIsAvailable = computed(() => {
    return props.items.length > 0;
});
const getFileSize = computed(() => {
    const sizeInByte = props.items.reduce((value, items) => {
        const fileSize = typeof items.fileSize === 'number' ? items.fileSize : 0;

        return Number(value) + fileSize;
    }, 0);

    return Contena.Utils.format.fileSize(sizeInByte);
});
const getFileSizeLabel = computed(() => {
    return `${t('ct-media.sidebar.metadata.totalSize')}: ${getFileSize.value}`;
});
const hasFolder = computed(() => {
    return props.items.some((item) => {
        return item.getEntityName() === 'media_folder';
    });
});
const hasMedia = computed(() => {
    return props.items.some((item) => {
        return item.getEntityName() === 'media';
    });
});
const isPrivate = computed(() => {
    return props.items.some((item) => {
        return item.private === true;
    });
});

const onRemoveItemFromSelection = (event) => {
    emit('media-item-selection-remove', event);
};
const quickActionClassesDelete = (disabled) => {
    return [
        'ct-media-sidebar__quickaction',
        {
            'ct-media-sidebar__quickaction--disabled': disabled,
        },
    ];
};
const quickActionClasses = (disabled) => {
    return [
        'ct-media-sidebar__quickaction',
        {
            'ct-media-sidebar__quickaction--disabled': disabled,
        },
    ];
};
const {
    showModalDelete,
    showFolderDissolve,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openFolderDissolve,
    closeFolderDissolve,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderDissolved,
    onFolderMoved,
} = useMediaSidebarModal({
    onItemsDelete: (ids) => emit('media-sidebar-items-delete', ids),
    onFolderItemsDissolve: (ids) => emit('media-sidebar-folder-items-dissolve', ids),
    onItemsMove: (ids) => emit('media-sidebar-items-move', ids),
});

swDefinePublic({
    itemsIsAvailable,
    getFileSize,
    getFileSizeLabel,
    hasFolder,
    hasMedia,
    isPrivate,
    onRemoveItemFromSelection,
    quickActionClassesDelete,
    quickActionClasses,
    showModalDelete,
    showFolderDissolve,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openFolderDissolve,
    closeFolderDissolve,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderDissolved,
    onFolderMoved,
});

defineExpose({
    itemsIsAvailable,
    getFileSize,
    getFileSizeLabel,
    hasFolder,
    hasMedia,
    isPrivate,
    onRemoveItemFromSelection,
    quickActionClassesDelete,
    quickActionClasses,
    showModalDelete,
    showFolderDissolve,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openFolderDissolve,
    closeFolderDissolve,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderDissolved,
    onFolderMoved,
});
</script>
