<template>
    <ct-block name="sw_media_modal_v2">
        <ct-modal
            ref="swMediaModal"
            class="ct-media-save-modal"
            variant="full"
            :title="$t('ct-media.ct-media-save-modal.titleModal')"
            :closable="!isLoading"
            @modal-close="onEmitModalClosed"
        >
            <ct-block name="sw_media_save_modal_content">
                <div class="ct-media-save-modal__content">
                    <ct-block name="sw_media_save_modal_navigation_and_search">
                        <div class="ct-media-save-modal__breadcrumbs-and-search">
                            <ct-block name="sw_media_save_modal_folder_breadcrumbs">
                                <ct-media-breadcrumbs
                                    v-model:current-folder-id="folderId"
                                    :small="compact"
                                    :disabled="isLoading"
                                />
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_media_save_modal_media_library">
                        <ct-media-library
                            ref="mediaLibrary"
                            :selection="[]"
                            :folder-id="folderId"
                            :compact="compact"
                            :disabled="isLoading"
                            allow-create-folder
                            :allow-multi-select="false"
                            @media-folder-change="folderId = $event"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_media_save_modal_modal_footer">
                    <ct-block name="sw_media_save_modal_input_file_name">
                        <mt-text-field
                            v-model="fileName"
                            class="ct-media-save-modal__input-file-name"
                            size="small"
                            :disabled="isLoading"
                        >
                            <template #suffix> .{{ fileType.toLowerCase() }} </template>
                        </mt-text-field>
                    </ct-block>

                    <ct-block name="sw_media_save_modal_button_cancel">
                        <mt-button
                            variant="secondary"
                            class="ct-media-save-modal__button-cancel"
                            :disabled="isLoading"
                            @click="onEmitModalClosed"
                        >
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_media_save_modal_button_save">
                        <mt-button
                            variant="primary"
                            class="ct-media-save-modal__button-save"
                            :is-loading="isLoading"
                            @click="onSaveMedia"
                        >
                            {{ $t('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-media-save-modal.scss';
const { Context } = Contena;

const props = defineProps({
    initialFolderId: {
        type: String,
        required: false,
        default: null,
    },

    initialFileName: {
        type: String,
        required: false,
        default: null,
    },

    fileType: {
        type: String,
        required: false,
        default: 'png',
    },
});
const emit = defineEmits([
    'save-media',
    'modal-close',
]);

import { ref, computed, inject, watch, onMounted, onBeforeUnmount } from 'vue';

const swMediaModal = ref(null);

const repositoryFactory = inject('repositoryFactory');

const fileName = ref(props.initialFileName || null);
const folderId = ref(props.initialFolderId || null);
const currentFolder = ref(null);
const compact = ref(false);
const selection = ref([]);
const isLoading = ref(false);

const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});

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
const addResizeListener = () => {
    window.addEventListener('resize', getComponentWidth);
};
const removeOnResizeListener = () => {
    window.removeEventListener('resize', getComponentWidth);
};
const getComponentWidth = () => {
    const componentWidth = swMediaModal.value?.$el?.getBoundingClientRect().width;
    compact.value = componentWidth <= 900;
};
const fetchCurrentFolder = async () => {
    if (!folderId.value) {
        currentFolder.value = null;
        return;
    }

    currentFolder.value = await mediaFolderRepository.value.get(folderId.value, Context.api);
};
const getMediaEntityForUpload = () => {
    const mediaItem = mediaRepository.value.create();
    mediaItem.mediaFolderId = folderId.value;
    mediaItem.private = false;

    return mediaItem;
};
const onSaveMedia = async () => {
    const targetEntity = getMediaEntityForUpload();
    isLoading.value = true;

    try {
        await mediaRepository.value.save(targetEntity, Context.api);

        emit('save-media', {
            fileName: fileName.value,
            folderId: folderId.value,
            mediaId: targetEntity.id,
        });

        onEmitModalClosed();
    } finally {
        isLoading.value = false;
    }
};
const onEmitModalClosed = () => {
    emit('modal-close');
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
    fileName,
    folderId,
    currentFolder,
    compact,
    selection,
    isLoading,
    mediaFolderRepository,
    mediaRepository,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    addResizeListener,
    removeOnResizeListener,
    getComponentWidth,
    fetchCurrentFolder,
    getMediaEntityForUpload,
    onSaveMedia,
    onEmitModalClosed,
});

defineExpose({
    repositoryFactory,
    fileName,
    folderId,
    currentFolder,
    compact,
    selection,
    isLoading,
    mediaFolderRepository,
    mediaRepository,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    addResizeListener,
    removeOnResizeListener,
    getComponentWidth,
    fetchCurrentFolder,
    getMediaEntityForUpload,
    onSaveMedia,
    onEmitModalClosed,
});
</script>
