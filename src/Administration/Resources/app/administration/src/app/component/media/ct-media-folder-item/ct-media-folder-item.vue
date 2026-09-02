<template>
    <ct-block name="ct_media_folder_item">
        <ct-media-base-item
            class="ct-media-folder-item"
            v-bind="$attrs"
            :truncate-right="true"
            :allow-multi-select="!isParent"
        >
            <template #preview="{ item }">
                <ct-block name="ct_media_folder_item_preview">
                    <span
                        class="ct-media-folder-item__folder-symbol"
                        :class="{ 'is--parent': isParent, 'is--default': !!item.defaultFolderId }"
                        aria-hidden="true"
                    >
                        <mt-icon :name="isParent ? 'regular-chevron-left' : 'regular-folder'" size="20px" />
                    </span>
                </ct-block>
            </template>

            <template #name="{ item, isInlineEdit, endInlineEdit }">
                <ct-block name="ct_media_folder_name">
                    <ct-block name="ct_media_base_item_name">
                        <mt-text-field
                            v-if="!isParent && (isInlineEdit || item.isNew())"
                            v-autofocus
                            class="ct-media-base-item__name-field"
                            :model-value="item.name"
                            name="media-item-name"
                            @blur="onBlur($event, item, endInlineEdit)"
                            @click.stop
                            @keydown.enter="onBlur($event, item, endInlineEdit)"
                            @keyup.esc="endInlineEdit"
                        />
                        <div v-else ref="itemName" class="ct-media-base-item__name" :title="item.name">
                            {{ item.name }}
                        </div>
                    </ct-block>
                </ct-block>
            </template>

            <template #metadata="{ item }">
                <ct-block name="ct_media_folder_meta_data">
                    <div class="ct-media-folder-item__metadata">
                        <ct-time-ago :date="item.createdAt" :date-time-format="{ month: '2-digit', day: '2-digit' }" />
                    </div>
                </ct-block>
            </template>

            <template #context-menu="{ item, startInlineEdit, allowEdit, allowDelete }">
                <ct-block name="ct_media_folder_item_context_menu">
                    <ct-block name="ct_media_folder_item_context_item_show_media">
                        <ct-context-menu-item
                            class="ct-media-context-item__show-media-action"
                            @click="navigateToFolder(item.id)"
                        >
                            {{ $t('global.ct-media-folder-item.labelContextMenuShowMedia') }}
                        </ct-context-menu-item>
                    </ct-block>

                    <slot>
                        <ct-block name="ct_media_folder_item_additional_context_menu_slot"></ct-block>
                    </slot>

                    <ct-block name="ct_media_folder_item_context_group_quick_actions">
                        <div class="ct-context-menu__group">
                            <ct-block name="ct_media_folder_item_context_item_show_settings">
                                <ct-context-menu-item
                                    class="ct-media-context-item__open-settings-action"
                                    @click="openSettings"
                                >
                                    {{ $t('global.ct-media-folder-item.labelContextMenuShowSettings') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_folder_item_context_item_move">
                                <ct-context-menu-item
                                    :disabled="!allowEdit"
                                    class="ct-media-context-item__move-folder-action"
                                    @click="openMoveModal"
                                >
                                    {{ $t('global.ct-media-folder-item.labelContextMenuMove') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_folder_item_context_item_dissolve">
                                <ct-context-menu-item
                                    :disabled="!allowEdit"
                                    class="ct-media-context-item__dissolve-folder-action"
                                    @click="openDissolveModal"
                                >
                                    {{ $t('global.ct-media-folder-item.labelContextMenuDissolve') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_folder_item_context_item_rename_item">
                                <ct-context-menu-item
                                    :disabled="!allowEdit"
                                    class="ct-media-context-item__rename-folder-action"
                                    @click="startInlineEdit"
                                >
                                    {{ $t('global.ct-media-folder-item.labelContextMenuRename') }}
                                </ct-context-menu-item>
                            </ct-block>

                            <ct-block name="ct_media_folder_item_context_item_delete">
                                <ct-context-menu-item
                                    :disabled="!allowDelete"
                                    class="ct-media-context-item__delete-folder-action"
                                    variant="danger"
                                    @click="openDeleteModal"
                                >
                                    {{ $t('global.default.delete') }}
                                </ct-context-menu-item>
                            </ct-block>
                        </div>
                    </ct-block>
                </ct-block>
            </template>

            <template #modal-windows="{ item, allowEdit }">
                <ct-block name="ct_media_folder_modal_windows">
                    <ct-block name="ct_media_folder_settings_modal">
                        <ct-media-modal-folder-settings
                            v-if="showSettings"
                            :disabled="!allowEdit"
                            :media-folder-id="item.id"
                            @media-settings-modal-save="refreshIconConfig"
                            @media-settings-modal-close="closeSettings"
                        />
                    </ct-block>

                    <ct-block name="ct_media_folder_dissolve_modal">
                        <ct-media-modal-folder-dissolve
                            v-if="showDissolveModal"
                            :items-to-dissolve="[item]"
                            @media-folder-dissolve-modal-dissolve="onFolderDissolved"
                            @media-folder-dissolve-modal-close="closeDissolveModal"
                        />
                    </ct-block>

                    <ct-block name="ct_media_folder_move_modal">
                        <ct-media-modal-move
                            v-if="showMoveModal"
                            :items-to-move="[item]"
                            @media-move-modal-close="closeMoveModal"
                            @media-move-modal-items-move="onFolderMoved"
                        />
                    </ct-block>

                    <ct-block name="ct_media_folder_delete_modal">
                        <ct-media-modal-delete
                            v-if="showDeleteModal"
                            :items-to-delete="[item]"
                            @media-delete-modal-items-delete="emitItemDeleted"
                            @media-delete-modal-close="closeDeleteModal"
                        />
                    </ct-block>
                </ct-block>
            </template>
        </ct-media-base-item>
    </ct-block>
</template>

<script setup>
import './ct-media-folder-item.scss';
const { Application, Context } = Contena;
const { warn } = Contena.Utils.debug;

defineOptions({ inheritAttrs: false });

defineProps({
    isParent: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-folder-remove',
    'media-folder-changed',
    'media-folder-delete',
    'media-folder-dissolve',
    'media-folder-move',
]);

import { ref, computed, inject, nextTick, useAttrs } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const attrs = useAttrs();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const $attrs = attrs;
const repositoryFactory = inject('repositoryFactory');

const showSettings = ref(false);
const showDissolveModal = ref(false);
const showMoveModal = ref(false);
const showDeleteModal = ref(false);
const lastDefaultFolderId = ref(null);
const iconConfig = ref({
    name: '',
    color: 'inherit',
});

const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaDefaultFolderRepository = computed(() => {
    return repositoryFactory.create('media_default_folder');
});
const moduleFactory = computed(() => {
    return Application.getContainer('factory').module;
});
const mediaFolder = computed(() => {
    return attrs.item;
});
const iconName = computed(() => {
    switch (iconConfig.value.name) {
        case 'regular-box':
            return 'multicolor-folder-thumbnail--green';
        case 'regular-database':
            return 'multicolor-folder-thumbnail--grey';
        case 'regular-content':
            return 'multicolor-folder-thumbnail--pink';
        case 'regular-cog':
            return 'multicolor-folder-thumbnail--grey';
        default:
            return 'multicolor-folder-thumbnail';
    }
});
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const getIconConfigFromFolder = async () => {
    const folder = mediaFolder.value;

    if (!folder?.defaultFolderId || folder.defaultFolderId === lastDefaultFolderId.value) {
        return;
    }

    lastDefaultFolderId.value = folder.defaultFolderId;

    const defaultFolder = await mediaDefaultFolderRepository.value.get(folder.defaultFolderId, Context.api);

    if (!defaultFolder) {
        return;
    }

    const module = moduleFactory.value.getModuleByEntityName(defaultFolder.entity);

    if (!module) {
        warn('Missing module for default folder entity', defaultFolder.entity);
        return;
    }

    iconConfig.value = {
        name: module.manifest?.icon ?? '',
        color: module.manifest?.color ?? '#000000',
    };
};
const createdComponent = () => {
    void getIconConfigFromFolder();
};
const onChangeName = async (updatedName, item, endInlineEdit) => {
    if (!updatedName || !updatedName.trim()) {
        rejectRenaming(item, 'empty-name', endInlineEdit);
        return;
    }

    if (updatedName.includes('<')) {
        rejectRenaming(item, 'invalid-name', endInlineEdit);
        return;
    }

    item.name = updatedName;

    try {
        await mediaFolderRepository.value.save(item, Context.api);
        item._isNew = false;
        emit('media-folder-changed');
    } catch (error) {
        rejectRenaming(item, error, endInlineEdit);
    } finally {
        endInlineEdit();
    }
};
const onBlur = (event, item, endInlineEdit) => {
    const input = event.target.value;

    if (input !== item.name) {
        void onChangeName(input, item, endInlineEdit);
        return;
    }

    endInlineEdit();
};
function rejectRenaming(item, cause, endInlineEdit) {
    if (cause) {
        let title = t('global.default.error');
        let message = t('global.ct-media-folder-item.notification.renamingError.message');
        if (cause === 'empty-name') {
            title = t('global.default.error');
            message = t('global.ct-media-folder-item.notification.errorBlankItemName.message');
        } else if (cause === 'invalid-name') {
            title = t('global.default.error');
            message = t('global.ct-media-folder-item.notification.errorInvalidItemName.message');
        }
        createNotificationError({
            title: title,
            message: message,
        });
    }
    if (item.isNew() === true) {
        emit('media-folder-remove', [item.id]);
    }
    endInlineEdit();
}
const navigateToFolder = (id) => {
    void router.push({
        name: 'ct.media.index',
        params: {
            folderId: id,
        },
    });
};
const openSettings = () => {
    showSettings.value = true;
};
const closeSettings = (mediaFolderChanged) => {
    showSettings.value = false;

    // The boolean check if necessary, because sometimes the original html event is passed as an argument
    if (typeof mediaFolderChanged === 'boolean' && mediaFolderChanged === true) {
        void nextTick(() => {
            emit('media-folder-changed');
        });
    }
};
const openDissolveModal = () => {
    showDissolveModal.value = true;
};
const closeDissolveModal = () => {
    showDissolveModal.value = false;
};
const openDeleteModal = () => {
    showDeleteModal.value = true;
};
const closeDeleteModal = () => {
    showDeleteModal.value = false;
};
const emitItemDeleted = (ids) => {
    closeDeleteModal();

    void nextTick(() => {
        emit('media-folder-delete', ids.folderIds);
    });
};
const onFolderDissolved = (ids) => {
    closeDissolveModal();

    void nextTick(() => {
        emit('media-folder-dissolve', ids);
    });
};
const onFolderMoved = (ids) => {
    closeMoveModal();

    void nextTick(() => {
        emit('media-folder-move', ids);
    });
};
const openMoveModal = () => {
    showMoveModal.value = true;
};
function closeMoveModal() {
    showMoveModal.value = false;
}
const refreshIconConfig = async () => {
    await getIconConfigFromFolder();
    closeSettings(true);
};

createdComponent();

ctDefinePublic({
    repositoryFactory,
    showSettings,
    showDissolveModal,
    showMoveModal,
    showDeleteModal,
    lastDefaultFolderId,
    iconConfig,
    mediaFolderRepository,
    mediaDefaultFolderRepository,
    moduleFactory,
    mediaFolder,
    iconName,
    assetFilter,
    createdComponent,
    getIconConfigFromFolder,
    onChangeName,
    onBlur,
    rejectRenaming,
    navigateToFolder,
    openSettings,
    closeSettings,
    openDissolveModal,
    closeDissolveModal,
    openDeleteModal,
    closeDeleteModal,
    emitItemDeleted,
    onFolderDissolved,
    onFolderMoved,
    openMoveModal,
    closeMoveModal,
    refreshIconConfig,
});

defineExpose({
    repositoryFactory,
    showSettings,
    showDissolveModal,
    showMoveModal,
    showDeleteModal,
    lastDefaultFolderId,
    iconConfig,
    mediaFolderRepository,
    mediaDefaultFolderRepository,
    moduleFactory,
    mediaFolder,
    iconName,
    assetFilter,
    createdComponent,
    getIconConfigFromFolder,
    onChangeName,
    onBlur,
    rejectRenaming,
    navigateToFolder,
    openSettings,
    closeSettings,
    openDissolveModal,
    closeDissolveModal,
    openDeleteModal,
    closeDeleteModal,
    emitItemDeleted,
    onFolderDissolved,
    onFolderMoved,
    openMoveModal,
    closeMoveModal,
    refreshIconConfig,
});
</script>
