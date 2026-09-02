<template>
    <ct-block name="ct_media_folder_info">
        <div class="ct-media-folder-info">
            <ct-block name="ct_media_quickinfo_folder_quickactions">
                <ct-media-collapse
                    v-if="editable"
                    :title="$t('ct-media.sidebar.sections.actions')"
                    :expand-on-loading="true"
                >
                    <template #content>
                        <ct-block name="ct_media_quickinfo_folder_quickactions_content">
                            <ul class="ct-media-sidebar__quickactions-list">
                                <ct-block name="ct_media_quickinfo_folder_quickactions_move">
                                    <li
                                        v-tooltip="{
                                            message: $t('ct-privileges.tooltip.warning'),
                                            disabled: acl.can('media.editor'),
                                            showOnDisabledElements: true,
                                        }"
                                        class="quickaction--move"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalMove"
                                        @keydown.enter="openModalMove"
                                    >
                                        <mt-icon
                                            class="ct-media-sidebar__quickactions-icon"
                                            size="16px"
                                            name="regular-file-export"
                                        />
                                        {{ $t('ct-media.sidebar.actions.move') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_folder_quickactions_settings">
                                    <li
                                        class="ct-media-sidebar__quickaction quickaction--settings"
                                        role="button"
                                        tabindex="0"
                                        @click="openFolderSettings"
                                        @keydown.enter="openFolderSettings"
                                    >
                                        <mt-icon
                                            class="ct-media-sidebar__quickactions-icon"
                                            size="16px"
                                            name="regular-cog"
                                        />
                                        {{ $t('ct-media.sidebar.actions.settings') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_folder_quickactions_dissolve">
                                    <li
                                        v-tooltip="{
                                            message: $t('ct-privileges.tooltip.warning'),
                                            disabled: acl.can('media.editor'),
                                            showOnDisabledElements: true,
                                        }"
                                        class="quickaction--dissolve"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openFolderDissolve"
                                        @keydown.enter="openFolderDissolve"
                                    >
                                        <mt-icon
                                            class="ct-media-sidebar__quickactions-icon"
                                            size="16px"
                                            name="regular-spinner-star"
                                        />
                                        {{ $t('ct-media.sidebar.actions.dissolve') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_folder_quickactions_delete">
                                    <li
                                        v-tooltip="{
                                            message: $t('ct-privileges.tooltip.warning'),
                                            disabled: acl.can('media.deleter'),
                                            showOnDisabledElements: true,
                                        }"
                                        class="quickaction--delete is--danger"
                                        :class="quickActionClasses(!acl.can('media.deleter'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalDelete"
                                        @keydown.enter="openModalDelete"
                                    >
                                        <mt-icon
                                            class="ct-media-sidebar__quickactions-icon is--danger"
                                            size="16px"
                                            name="regular-trash"
                                        />
                                        {{ $t('ct-media.sidebar.actions.delete') }}
                                    </li>
                                </ct-block>
                            </ul>
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_quickinfo_folder_metadata">
                <ct-media-collapse :expand-on-loading="true" :title="$t('ct-media.sidebar.sections.metadata')">
                    <template #content>
                        <ct-block name="ct_media_quickinfo_folder_metadata_content">
                            <dl class="ct-media-sidebar__metadata-list">
                                <ct-block name="ct_media_quickinfo_folder_metadata_content_base">
                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-name"
                                        :class="nameItemClasses"
                                        :label-name="$t('ct-media.sidebar.metadata.name')"
                                        :truncated="false"
                                    >
                                        <ct-confirm-field
                                            v-if="editable"
                                            ref="inlineEditFieldName"
                                            :disabled="!acl.can('media.creator')"
                                            compact
                                            :value="mediaFolder.name"
                                            :error="mediaFolderNameError"
                                            @input="onChangeFolderName"
                                        />
                                        <template v-else>
                                            {{ mediaFolder.name }}
                                        </template>
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-createdAt"
                                        :label-name="$t('ct-media.sidebar.metadata.createdAt')"
                                    >
                                        {{ createdAt }}
                                    </ct-media-quickinfo-metadata-item>
                                </ct-block>
                            </dl>
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_folder_info_settings_modal">
                <ct-media-modal-folder-settings
                    v-if="showFolderSettings"
                    :disabled="!acl.can('media.editor')"
                    :media-folder-id="mediaFolder.id"
                    @media-settings-modal-save="closeFolderSettings"
                    @media-settings-modal-close="closeFolderSettings"
                />
            </ct-block>

            <ct-block name="ct_media_folder_info_dissolve_modal">
                <ct-media-modal-folder-dissolve
                    v-if="showFolderDissolve"
                    :items-to-dissolve="[mediaFolder]"
                    @media-folder-dissolve-modal-dissolve="onFolderDissolved"
                    @media-folder-dissolve-modal-close="closeFolderDissolve"
                />
            </ct-block>

            <ct-block name="ct_media_folder_info_move_modal">
                <ct-media-modal-move
                    v-if="showModalMove"
                    :items-to-move="[mediaFolder]"
                    @media-move-modal-close="closeModalMove"
                    @media-move-modal-items-move="onFolderMoved"
                />
            </ct-block>

            <ct-block name="ct_media_folder_info_modal_delete">
                <ct-media-modal-delete
                    v-if="showModalDelete"
                    :items-to-delete="[mediaFolder]"
                    @media-delete-modal-close="closeModalDelete"
                    @media-delete-modal-items-delete="deleteSelectedItems"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-folder-info.scss';
const { Context } = Contena;

const props = defineProps({
    mediaFolder: {
        type: Object,
        required: true,
        validator(value) {
            return value.getEntityName() === 'media_folder';
        },
    },

    editable: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-folder-renamed',
    'media-sidebar-items-delete',
    'media-sidebar-folder-items-dissolve',
    'media-sidebar-items-move',
]);

import { computed, inject } from 'vue';
import useMediaSidebarModal from 'src/app/composables/use-media-sidebar-modal';

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const createdAt = computed(() => {
    return Contena.Utils.format.date(props.mediaFolder.createdAt);
});
const mediaFolderNameError = computed(() => {
    const entity = props.mediaFolder;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const nameItemClasses = computed(() => {
    return {
        'has--error': !!mediaFolderNameError.value,
    };
});

const onChangeFolderName = async (newName) => {
    const mediaFolder = props.mediaFolder;
    mediaFolder.name = newName;
    await mediaFolderRepository.value.save(mediaFolder, Context.api);
    emit('media-folder-renamed');
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
    showFolderSettings,
    showFolderDissolve,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openFolderSettings,
    closeFolderSettings,
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

ctDefinePublic({
    repositoryFactory,
    acl,
    mediaFolderRepository,
    createdAt,
    mediaFolderNameError,
    nameItemClasses,
    onChangeFolderName,
    quickActionClasses,
    showModalDelete,
    showFolderSettings,
    showFolderDissolve,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openFolderSettings,
    closeFolderSettings,
    openFolderDissolve,
    closeFolderDissolve,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderDissolved,
    onFolderMoved,
});

defineExpose({
    repositoryFactory,
    acl,
    mediaFolderRepository,
    createdAt,
    mediaFolderNameError,
    nameItemClasses,
    onChangeFolderName,
    quickActionClasses,
    showModalDelete,
    showFolderSettings,
    showFolderDissolve,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openFolderSettings,
    closeFolderSettings,
    openFolderDissolve,
    closeFolderDissolve,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderDissolved,
    onFolderMoved,
});
</script>
