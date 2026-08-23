<template>
    <ct-block name="sw_media_modal_v2">
        <mt-modal-root :is-open="isOpen" @change="onModalRootChange">
            <mt-modal
                ref="swMediaModal"
                class="ct-media-modal-v2"
                width="full"
                :title="translate('ct-media.ct-media-modal-v2.titleModal')"
            >
                <ct-block name="sw_media_modal_v2_content">
                    <div class="ct-media-modal-v2__content">
                        <ct-block name="sw_media_modal_v2_tabs">
                            <div position-identifier="ct-media-modal" class="ct-media-modal-v2__tabs">
                                <mt-tabs :items="tabItems" :default-item="defaultTab" @new-item-active="onTabChange" />

                                <ct-block name="sw_media_modal_v2_tab_content">
                                    <div class="ct-media-modal-v2__tab-content">
                                        <ct-block name="sw_media_modal_v2_tab_content_library">
                                            <div
                                                v-show="activeTab === tabNameLibrary"
                                                class="ct-media-modal-v2__library-content"
                                            >
                                                <ct-block name="sw_media_modal_v2_navigation_and_search">
                                                    <div
                                                        class="ct-media-modal-v2__breadcrumbs-and-search"
                                                        :class="{
                                                            'ct-media-modal-v2__breadcrumbs-and-search--compact': compact,
                                                        }"
                                                    >
                                                        <ct-block name="sw_media_modal_v2_folder_breadcrumbs">
                                                            <ct-media-breadcrumbs
                                                                v-model:current-folder-id="folderId"
                                                                :small="compact"
                                                            />
                                                        </ct-block>

                                                        <ct-block name="sw_media_modal_v2_search_field">
                                                            <mt-text-field
                                                                class="ct-media-modal-v2__search-field"
                                                                name="mediaModalSearch"
                                                                size="small"
                                                                :model-value="term"
                                                                :placeholder="
                                                                    translate('ct-media.general.placeholderSearchBar')
                                                                "
                                                                @update:model-value="onSearchTermChange"
                                                            >
                                                                <template #prefix>
                                                                    <mt-icon name="regular-search" />
                                                                </template>
                                                            </mt-text-field>
                                                        </ct-block>
                                                    </div>
                                                </ct-block>

                                                <ct-block name="sw_media_modal_v2_media_library">
                                                    <ct-media-library
                                                        ref="mediaLibrary"
                                                        :selection="selection"
                                                        :folder-id="folderId"
                                                        :term="term"
                                                        :compact="compact"
                                                        hide-search
                                                        :allow-multi-select="allowMultiSelect"
                                                        @update:selection="selection = $event"
                                                        @media-folder-change="folderId = $event"
                                                        @media-term-change="onSearchTermChange"
                                                    />
                                                </ct-block>
                                            </div>
                                        </ct-block>

                                        <ct-block name="sw_media_modal_v2_tab_content_upload">
                                            <div
                                                v-show="activeTab === tabNameUpload"
                                                class="ct-media-modal-v2__uploads-content"
                                            >
                                                <ct-block name="sw_media_modal_v2_upload_component">
                                                    <ct-upload-listener
                                                        :upload-tag="uploadTag"
                                                        @media-upload-add="onUploadsAdded"
                                                        @media-upload-finish="onUploadFinished"
                                                        @media-upload-fail="onUploadFailed"
                                                    />

                                                    <ct-media-upload-v2
                                                        class="ct-media-modal-v2__upload-container"
                                                        variant="regular"
                                                        :file-accept="fileAccept"
                                                        :upload-tag="uploadTag"
                                                        :default-folder="entityContext"
                                                        :target-folder-id="folderId"
                                                        :allow-multi-select="allowMultiSelect"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_media_modal_v2_uploaded_items">
                                                    <ct-media-grid
                                                        :presentation="compact ? 'list-preview' : 'medium-preview'"
                                                        :class="{ 'ct-media-modal-v2__upload-media-grid--compact': compact }"
                                                    >
                                                        <ct-media-media-item
                                                            v-for="upload in uploads"
                                                            :key="`ct-media-modal-v2-upload-${upload.id}`"
                                                            :item="upload"
                                                            :show-context-menu-button="false"
                                                            :show-selection-indicator="allowMultiSelect"
                                                            :allow-multi-select="allowMultiSelect"
                                                            :selected="checkMediaItem(upload)"
                                                            :editable="false"
                                                            :is-list="compact"
                                                            @media-item-selection-remove="onMediaRemoveSelected"
                                                            @media-item-selection-add="onMediaAddSelected"
                                                            @media-item-click="onMediaItemSelect"
                                                        />
                                                    </ct-media-grid>
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="sw_media_modal_v2_media_sidebar">
                            <ct-media-sidebar
                                v-if="selection.length > 0"
                                :items="selection"
                                :current-folder-id="null"
                                @media-sidebar-items-delete="onItemsDeleted"
                                @media-sidebar-folder-items-dissolve="onMediaFoldersDissolved"
                                @media-sidebar-items-move="refreshList"
                                @media-item-selection-remove="onMediaRemoveSelected"
                                @media-sidebar-close="resetSelection"
                            />
                        </ct-block>
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="sw_media_modal_v2_modal_footer">
                        <div class="ct-media-modal-v2__footer">
                            <ct-block name="sw_media_modal_v2_button_cancel">
                                <mt-button variant="secondary" @click="onEmitModalClosed">
                                    {{ translate('global.default.cancel') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="sw_media_modal_v2_button_confirm_selection">
                                <mt-button variant="primary" :disabled="selection.length < 1" @click="onEmitSelection">
                                    {{ translate('ct-media.ct-media-modal-v2.labelButtonSaveSelection') }}
                                </mt-button>
                            </ct-block>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup>
import './ct-media-modal-v2.scss';
const { Context, Utils } = Contena;

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: false,
        default: true,
    },

    initialFolderId: {
        type: String,
        required: false,
        default: null,
    },

    entityContext: {
        type: String,
        required: false,
        default: null,
    },

    defaultTab: {
        type: String,
        required: false,
        validValues: [
            'upload',
            'library',
        ],
        default: 'library',
        validator(value) {
            return [
                'upload',
                'library',
            ].includes(value);
        },
    },

    allowMultiSelect: {
        type: Boolean,
        required: false,
        default: true,
    },

    fileAccept: {
        type: String,
        required: false,
        default: 'image/*',
    },
});
const emit = defineEmits([
    'modal-close',
    'media-modal-selection-change',
]);

import { ref, computed, inject, watch, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const translate = t;
const mediaLibrary = ref(null);
const swMediaModal = ref(null);

const repositoryFactory = inject('repositoryFactory');
const mediaService = inject('mediaService');

const selection = ref([]);
const uploads = ref([]);
const folderId = ref(props.initialFolderId);
const currentFolder = ref(null);
const compact = ref(false);
const term = ref('');
const id = ref(Utils.createId());
const selectedMediaItem = ref({});
const activeTab = ref(props.defaultTab);

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const tabNameUpload = computed(() => {
    return 'upload';
});
const tabNameLibrary = computed(() => {
    return 'library';
});
const hasUploads = computed(() => {
    return uploads.value.length > 0;
});
const tabItems = computed(() => {
    return [
        {
            label: t('ct-media.ct-media-modal-v2.labelTabItemLibrary'),
            name: tabNameLibrary.value,
            disabled: hasUploads.value,
        },
        {
            label: t('ct-media.ct-media-modal-v2.labelTabItemUpload'),
            name: tabNameUpload.value,
        },
    ];
});
const uploadTag = computed(() => {
    return `ct-media-modal-v2--${id.value}`;
});

const onTabChange = (tab) => {
    activeTab.value = tab;

    if (tab === tabNameUpload.value) {
        resetSelection();
    }
};
const createdComponent = () => {
    void fetchCurrentFolder();
    addResizeListener();
};
const mountedComponent = () => {
    getComponentWidth();
};
const beforeDestroyComponent = () => {
    removeOnResizeListener();
};
const fetchCurrentFolder = async () => {
    if (!folderId.value) {
        currentFolder.value = null;
        return;
    }

    currentFolder.value = await mediaFolderRepository.value.get(folderId.value, Context.api);
};
const addResizeListener = () => {
    window.addEventListener('resize', getComponentWidth);
};
const removeOnResizeListener = () => {
    window.removeEventListener('resize', getComponentWidth);
};
const getComponentWidth = () => {
    // during teleportation the $el doesn't have a bounding client rect yet
    const componentWidth = swMediaModal.value?.$el?.getBoundingClientRect?.().width;
    if (!componentWidth) {
        return;
    }

    compact.value = componentWidth <= 900;
};
const onModalRootChange = (isOpen) => {
    if (!isOpen) {
        emit('modal-close');
    }
};
const onEmitModalClosed = () => {
    emit('modal-close');
};
const onEmitSelection = () => {
    // emit media items only
    const selectedMedia = selection.value.filter((selected) => {
        return selected.getEntityName() === 'media';
    });

    emit('media-modal-selection-change', selectedMedia);
    onEmitModalClosed();
};
const refreshList = () => {
    mediaLibrary.value.refreshList();
};
const onMediaRemoveSelected = ({ item }) => {
    const index = selection.value.findIndex((selectedItem) => {
        return item.id === selectedItem.id;
    });
    if (index === -1) {
        return;
    }

    selection.value.splice(index, 1);
};
const onMediaAddSelected = ({ item }) => {
    if (selection.value.includes(item)) {
        return;
    }

    selection.value.push(item);
};
const onMediaItemSelect = ({ item }) => {
    if (!props.allowMultiSelect) {
        selection.value = [item];
        selectedMediaItem.value = item;
    }
};
const resetSelection = () => {
    selection.value.splice(0, selection.value.length);
};
const onItemsDeleted = (ids) => {
    onMediaFoldersDissolved(ids.folderIds);
};
const onMediaFoldersDissolved = (folderIds) => {
    if (!currentFolder.value) {
        return;
    }

    if (
        folderIds.some((dissolvedId) => {
            return dissolvedId === currentFolder.value.id;
        })
    ) {
        folderId.value = currentFolder.value.parentId;
    }

    refreshList();
};
const onUploadsAdded = async () => {
    await mediaService.runUploads(uploadTag.value);
};
const onUploadFinished = async ({ targetId }) => {
    const updatedMedia = await mediaRepository.value.get(targetId, Context.api);
    selectedMediaItem.value = updatedMedia;

    if (
        !uploads.value.some((upload) => {
            return updatedMedia.id === upload.id;
        })
    ) {
        uploads.value.push(updatedMedia);
    }

    if (props.allowMultiSelect) {
        const foundSelectedItem = selection.value.some((selectedItem) => {
            return updatedMedia.id === selectedItem.id;
        });
        if (!foundSelectedItem) {
            selection.value.push(updatedMedia);
        }
    } else {
        selection.value = [updatedMedia];
    }
};
const onUploadFailed = (task) => {
    uploads.value = uploads.value.filter((selectedUpload) => {
        return selectedUpload.id !== task.targetId;
    });
};
const selectMediaItem = (upload) => {
    if (props.allowMultiSelect) {
        return;
    }

    selectedMediaItem.value = upload;
    selection.value = [upload];
};
const checkMediaItem = (upload) => {
    if (props.allowMultiSelect) {
        return selection.value.includes(upload);
    }

    return upload.id === selectedMediaItem.value.id;
};
const onSearchTermChange = (searchTerm) => {
    term.value = searchTerm;
};

watch(
    () => folderId.value,
    () => {
        void fetchCurrentFolder();
    },
);

createdComponent();

onMounted(() => {
    mountedComponent();
});
onBeforeUnmount(() => {
    beforeDestroyComponent();
});

swDefinePublic({
    repositoryFactory,
    mediaService,
    selection,
    uploads,
    folderId,
    currentFolder,
    compact,
    term,
    id,
    selectedMediaItem,
    activeTab,
    mediaRepository,
    mediaFolderRepository,
    tabNameUpload,
    tabNameLibrary,
    hasUploads,
    tabItems,
    uploadTag,
    onTabChange,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    fetchCurrentFolder,
    addResizeListener,
    removeOnResizeListener,
    getComponentWidth,
    onModalRootChange,
    onEmitModalClosed,
    onEmitSelection,
    refreshList,
    onMediaRemoveSelected,
    onMediaAddSelected,
    onMediaItemSelect,
    resetSelection,
    onItemsDeleted,
    onMediaFoldersDissolved,
    onUploadsAdded,
    onUploadFinished,
    onUploadFailed,
    selectMediaItem,
    checkMediaItem,
    onSearchTermChange,
});

defineExpose({
    repositoryFactory,
    mediaService,
    selection,
    uploads,
    folderId,
    currentFolder,
    compact,
    term,
    id,
    selectedMediaItem,
    activeTab,
    mediaRepository,
    mediaFolderRepository,
    tabNameUpload,
    tabNameLibrary,
    hasUploads,
    tabItems,
    uploadTag,
    onTabChange,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    fetchCurrentFolder,
    addResizeListener,
    removeOnResizeListener,
    getComponentWidth,
    onModalRootChange,
    onEmitModalClosed,
    onEmitSelection,
    refreshList,
    onMediaRemoveSelected,
    onMediaAddSelected,
    onMediaItemSelect,
    resetSelection,
    onItemsDeleted,
    onMediaFoldersDissolved,
    onUploadsAdded,
    onUploadFinished,
    onUploadFailed,
    selectMediaItem,
    checkMediaItem,
    onSearchTermChange,
});
</script>
