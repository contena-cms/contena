<template>
    <ct-block name="ct_media_media_item">
        <ct-media-base-item
            class="ct-media-media-item"
            :item="item"
            v-bind="$attrs"
            @media-item-click="emit('media-item-click', $event)"
            @media-item-selection-add="emit('media-item-selection-add', $event)"
            @media-item-selection-remove="emit('media-item-selection-remove', $event)"
        >
            <template #preview="{ item }">
                <ct-block name="ct_media_media_item_preview">
                    <div class="ct-media-media-item__preview-hit-area" @click.stop="emitItemClick($event, item)">
                        <ct-media-preview-v2
                            :source="item"
                            :media-is-private="item.private"
                            @media-preview-play="emitPlayEvent($event, item)"
                        />
                    </div>
                </ct-block>
            </template>

            <template #name="{ item, isInlineEdit, startInlineEdit, endInlineEdit }">
                <ct-block name="ct_media_media_item_name_container">
                    <mt-text-field
                        v-if="isInlineEdit"
                        v-autofocus
                        class="ct-media-base-item__name-field"
                        :model-value="item.fileName"
                        name="media-item-name"
                        @blur="onBlur($event, item, endInlineEdit)"
                        @keyup.esc="endInlineEdit"
                        @click.stop
                        @keydown.enter="endInlineEdit"
                    />
                    <div
                        v-else
                        ref="itemName"
                        class="ct-media-base-item__name"
                        :title="`${item.fileName}.${item.fileExtension}`"
                        role="menuitem"
                        tabindex="0"
                        @click.stop="emitItemClick($event, item)"
                        @dblclick="startInlineEdit"
                    >
                        <template v-if="item.hasFile">
                            {{ mediaNameFilter(item) }}
                        </template>
                    </div>
                </ct-block>
            </template>

            <template #metadata="{ item }">
                <ct-block name="ct_media_media_item_metadata">
                    <div class="ct-media-media-item__metadata">
                        <span v-if="item.uploadedAt"
                            ><ct-time-ago
                                :date="item.uploadedAt"
                                :date-time-format="{ month: '2-digit', day: '2-digit' }"
                            />, </span
                        >{{ fileSizeFilter(item.fileSize, locale) }}
                    </div>
                </ct-block>
            </template>

            <template #context-menu="{ item, startInlineEdit, allowEdit, allowDelete }">
                <ct-block name="ct_media_media_item_context_menu">
                    <slot>
                        <ct-block name="ct_media_media_item_additional_context_menu_slot"></ct-block>
                    </slot>

                    <ct-block name="ct_media_media_item_context_group_quick_actions">
                        <div :class="defaultContextMenuClass">
                            <ct-block name="ct_media_media_item_context_item_rename_item">
                                <ct-context-menu-item
                                    :disabled="!item.hasFile || item.private || !allowEdit"
                                    @click="startInlineEdit"
                                >
                                    {{ translate('global.ct-media-media-item.labelContextMenuRename') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_media_item_context_item_copy_item_link">
                                <ct-context-menu-item v-if="item.hasFile" @click="copyItemLink(item)">
                                    {{ translate('global.ct-media-media-item.labelContextMenuCopyLink') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_media_item_context_item_replace">
                                <ct-context-menu-item
                                    :disabled="item.private || !allowEdit"
                                    class="ct-media-context-item__replace-media-action"
                                    @click="openModalReplace"
                                >
                                    {{ translate('global.ct-media-media-item.labelContextMenuReplace') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_media_item_context_item_move">
                                <ct-context-menu-item
                                    :disabled="!allowEdit"
                                    class="ct-media-context-item__move-media-action"
                                    @click="openModalMove"
                                >
                                    {{ translate('global.ct-media-media-item.labelContextMenuMove') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_media_item_context_item_set_cover">
                                <ct-context-menu-item
                                    v-if="isVideoMedia"
                                    :disabled="!allowEdit"
                                    class="ct-media-context-item__set-video-cover-action"
                                    @click="openCoverSelectionModal"
                                >
                                    {{ translate('global.ct-media-media-item.labelContextMenuSetCover') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_media_item_context_item_remove_cover">
                                <ct-context-menu-item
                                    v-if="isVideoMedia && hasVideoCover"
                                    :disabled="!allowEdit"
                                    class="ct-media-context-item__remove-video-cover-action"
                                    @click="removeVideoCover"
                                >
                                    {{ translate('global.ct-media-media-item.labelContextMenuRemoveCover') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_media_item_context_item_delete">
                                <ct-context-menu-item
                                    :disabled="item.private || !allowDelete"
                                    variant="danger"
                                    @click="openModalDelete"
                                >
                                    {{ translate('global.default.delete') }}
                                </ct-context-menu-item>
                            </ct-block>
                        </div>
                    </ct-block>
                </ct-block>
            </template>

            <template #modal-windows="{ item, allowEdit, allowDelete }">
                <ct-block name="ct_media_media_item_modal_replace">
                    <ct-media-modal-replace
                        v-if="showModalReplace && allowEdit"
                        :item-to-replace="item"
                        @media-replace-modal-item-replaced="emitRefreshMediaLibrary"
                        @media-replace-modal-close="closeModalReplace"
                    />
                </ct-block>

                <ct-block name="ct_media_media_item_delete_modal">
                    <ct-media-modal-delete
                        v-if="showModalDelete && allowDelete"
                        :items-to-delete="[item]"
                        @media-delete-modal-items-delete="emitItemDeleted"
                        @media-delete-modal-close="closeModalDelete"
                    />
                </ct-block>

                <ct-block name="ct_media_media_item_move_modal">
                    <ct-media-modal-move
                        v-if="showModalMove && allowEdit"
                        :items-to-move="[item]"
                        @media-move-modal-close="closeModalMove"
                        @media-move-modal-items-move="onMediaItemMoved"
                    />
                </ct-block>

                <ct-block name="ct_media_media_item_cover_modal">
                    <ct-media-modal-v2
                        v-if="showCoverSelectionModal && allowEdit && isVideoMedia"
                        :allow-multi-select="false"
                        file-accept="image/*"
                        @modal-close="closeCoverSelectionModal"
                        @media-modal-selection-change="onCoverSelectionChange"
                    />
                </ct-block>
            </template>
        </ct-media-base-item>
    </ct-block>
</template>

<script setup>
import './ct-media-media-item.scss';
const { dom } = Contena.Utils;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits([
    'media-item-click',
    'media-item-selection-add',
    'media-item-rename-success',
    'media-item-play',
    'media-item-delete',
    'media-folder-move',
    'media-item-replaced',
    'media-item-selection-remove',
]);

import { ref, computed, inject, nextTick, useAttrs, useSlots } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import useVideoCover from 'src/app/composables/use-video-cover';

const slots = useSlots();
const attrs = useAttrs();
const { t, locale: currentLocale } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const mediaService = inject('mediaService');
const acl = inject('acl');

const showModalReplace = ref(false);
const showModalDelete = ref(false);
const showModalMove = ref(false);

const locale = computed(() => {
    return currentLocale.value;
});
const defaultContextMenuClass = computed(() => {
    return {
        'ct-context-menu__group': slots.default,
    };
});
const mediaNameFilter = computed(() => {
    return Contena.Filter.getByName('mediaName');
});
const fileSizeFilter = computed(() => {
    return Contena.Filter.getByName('fileSize');
});
const mediaRepository = computed(() => {
    return Contena.Service('repositoryFactory').create('media');
});

const onChangeName = async (updatedName, item, endInlineEdit) => {
    if (!updatedName || !updatedName.trim()) {
        rejectRenaming(endInlineEdit);
        return;
    }

    item.isLoading = true;

    try {
        await mediaService.renameMedia(item.id, updatedName);
        await mediaRepository.value.get(item.id).then((response) => {
            Object.assign(item, response);
        });

        item.isLoading = false;
        createNotificationSuccess({
            message: t('global.ct-media-media-item.notification.renamingSuccess.message'),
        });
        emit('media-item-rename-success', item);
    } catch (exception) {
        const errors = exception?.response?.data?.errors ?? [{ code: null }];

        errors.forEach((error) => {
            handleErrorMessage(error);
        });
    } finally {
        item.isLoading = false;
        endInlineEdit();
    }
};
function handleErrorMessage(error) {
    switch (error.code) {
        case 'CONTENT__MEDIA_FILE_NAME_IS_TOO_LONG':
            createNotificationError({
                message: t(
                    'global.ct-media-media-item.notification.fileNameTooLong.message',
                    {
                        length: error.meta.parameters.maxLength,
                    },
                    0,
                ),
            });
            break;
        default:
            createNotificationError({
                message: t('global.ct-media-media-item.notification.renamingError.message'),
            });
    }
}
function rejectRenaming(endInlineEdit) {
    createNotificationError({
        message: t('global.ct-media-media-item.notification.errorBlankItemName.message'),
    });
    endInlineEdit();
}
const onBlur = (event, item, endInlineEdit) => {
    const input = event.target.value;

    if (input !== item.fileName) {
        void onChangeName(input, item, endInlineEdit);
        return;
    }

    endInlineEdit();
};
const emitPlayEvent = (originalDomEvent, item) => {
    if (!attrs.selected) {
        emit('media-item-play', { originalDomEvent, item });
        return;
    }

    emit('media-item-selection-remove', { originalDomEvent, item });
};
const emitItemClick = (originalDomEvent, item) => {
    emit('media-item-click', { originalDomEvent, item });
};
const copyItemLink = async (item) => {
    try {
        await dom.copyStringToClipboard(item.url);
        createNotificationSuccess({
            message: t('ct-media.general.notification.urlCopied.message'),
        });
    } catch (_err) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.ct-field.notification.notificationCopyFailureMessage'),
        });
    }
};
const openModalDelete = () => {
    showModalDelete.value = true;
};
const closeModalDelete = () => {
    showModalDelete.value = false;
};
const emitItemDeleted = async (deletePromise) => {
    closeModalDelete();
    const ids = await deletePromise;
    emit('media-item-delete', ids.mediaIds);
};
const openModalReplace = () => {
    showModalReplace.value = true;
};
const closeModalReplace = () => {
    showModalReplace.value = false;
};
const openModalMove = () => {
    showModalMove.value = true;
};
const closeModalMove = () => {
    showModalMove.value = false;
};
const onMediaItemMoved = async (movePromise) => {
    closeModalMove();
    const ids = await movePromise;
    emit('media-folder-move', ids);
};
const emitRefreshMediaLibrary = () => {
    closeModalReplace();

    void nextTick(() => {
        emit('media-item-replaced');
    });
};
const {
    showCoverSelectionModal,
    isVideoMedia,
    hasVideoCover,
    openCoverSelectionModal,
    closeCoverSelectionModal,
    onCoverSelectionChange,
    removeVideoCover,
} = useVideoCover({
    item: () => props.item,
});

ctDefinePublic({
    mediaService,
    acl,
    showModalReplace,
    showModalDelete,
    showModalMove,
    showCoverSelectionModal,
    locale,
    defaultContextMenuClass,
    mediaNameFilter,
    fileSizeFilter,
    mediaRepository,
    onChangeName,
    handleErrorMessage,
    rejectRenaming,
    onBlur,
    emitPlayEvent,
    emitItemClick,
    copyItemLink,
    openModalDelete,
    closeModalDelete,
    emitItemDeleted,
    openModalReplace,
    closeModalReplace,
    openModalMove,
    closeModalMove,
    onMediaItemMoved,
    emitRefreshMediaLibrary,
    isVideoMedia,
    hasVideoCover,
    openCoverSelectionModal,
    closeCoverSelectionModal,
    onCoverSelectionChange,
    removeVideoCover,
});

defineExpose({
    mediaService,
    acl,
    showModalReplace,
    showModalDelete,
    showModalMove,
    showCoverSelectionModal,
    locale,
    defaultContextMenuClass,
    mediaNameFilter,
    fileSizeFilter,
    mediaRepository,
    onChangeName,
    handleErrorMessage,
    rejectRenaming,
    onBlur,
    emitPlayEvent,
    emitItemClick,
    copyItemLink,
    openModalDelete,
    closeModalDelete,
    emitItemDeleted,
    openModalReplace,
    closeModalReplace,
    openModalMove,
    closeModalMove,
    onMediaItemMoved,
    emitRefreshMediaLibrary,
    isVideoMedia,
    hasVideoCover,
    openCoverSelectionModal,
    closeCoverSelectionModal,
    onCoverSelectionChange,
    removeVideoCover,
});
</script>
