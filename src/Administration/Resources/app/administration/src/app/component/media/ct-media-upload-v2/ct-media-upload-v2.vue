<template>
    <ct-block name="sw_media_upload_v2">
        <div class="ct-media-upload-v2">
            <ct-block name="sw_media_upload_v2_compact">
                <div v-if="variant == 'compact'" class="ct-media-upload-v2__content">
                    <ct-button-group split-button>
                        <ct-block name="sw_media_upload_v2_compact_button_file_upload">
                            <mt-button
                                class="ct-media-upload-v2__button-compact-upload"
                                :disabled="disabled"
                                variant="primary"
                                size="default"
                                @click="onClickUpload"
                            >
                                {{ buttonFileUploadLabel }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="sw_media_upload_v2_compact_button_context_menu">
                            <ct-context-button
                                v-if="uploadUrlFeatureEnabled"
                                :disabled="disabled"
                                class="ct-media-upload-v2__button-open-context-menu"
                            >
                                <template #button>
                                    <mt-button
                                        :disabled="disabled"
                                        square
                                        variant="primary"
                                        size="default"
                                        class="ct-media-upload-v2__button-context-menu"
                                    >
                                        <mt-icon name="regular-chevron-down-xs" />
                                    </mt-button>
                                </template>

                                <ct-block name="sw_media_upload_v2_compact_button_context_menu_actions">
                                    <ct-context-menu-item
                                        class="ct-media-upload-v2__button-url-upload"
                                        @click="useUrlUpload"
                                    >
                                        {{ $t('global.ct-media-upload-v2.buttonUrlUpload') }}
                                    </ct-context-menu-item>
                                </ct-block>
                            </ct-context-button>
                        </ct-block>
                    </ct-button-group>

                    <ct-block name="sw_media_upload_v2_compact_url_form">
                        <ct-media-url-form
                            v-if="isUrlUpload"
                            variant="modal"
                            @modal-close="useFileUpload"
                            @media-url-form-submit="onUrlUpload"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_media_upload_v2_regular">
                <div v-if="variant == 'regular' || variant == 'small'" class="ct-media-upload-v2__content">
                    <ct-block name="sw_media_upload_v2_regular_header">
                        <div class="ct-media-upload-v2__header">
                            <ct-block name="sw_media_upload_v2_regular_header_label">
                                <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                                <label v-if="label" class="ct-media-upload-v2__label" :class="swFieldLabelClasses">
                                    {{ label }}
                                </label>
                            </ct-block>

                            <ct-block name="sw_media_upload_v2_regular_header_helptext">
                                <ct-help-text v-if="helpText" class="ct-media-upload-v2__help-text" :text="helpText" />
                            </ct-block>

                            <ct-block name="sw_media_upload_v2_regular_header_switch">
                                <ct-context-button
                                    v-if="!source && uploadUrlFeatureEnabled"
                                    class="ct-media-upload-v2__switch-mode"
                                    :disabled="disabled"
                                    aria-label="global.ct-media-upload-v2.switchMode"
                                >
                                    <ct-block name="sw_media_upload_v2_regular_header_switch_file_upload">
                                        <ct-context-menu-item
                                            v-if="!isFileUpload"
                                            :disabled="disabled"
                                            class="ct-media-upload-v2__button-file-upload"
                                            @click="useFileUpload"
                                        >
                                            {{ buttonFileUploadLabel }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="sw_media_upload_v2_regular_header_switch_url_upload">
                                        <ct-context-menu-item
                                            v-if="!isUrlUpload"
                                            class="ct-media-upload-v2__button-url-upload"
                                            @click="useUrlUpload"
                                        >
                                            {{ $t('global.ct-media-upload-v2.buttonUrlUpload') }}
                                        </ct-context-menu-item>
                                    </ct-block>
                                </ct-context-button>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_media_upload_v2_regular_drop_zone">
                        <div
                            ref="dropzone"
                            v-droppable="{ dragGroup: 'media', onDrop: onDropMedia, validDropCls: 'is--active' }"
                            class="ct-media-upload-v2__dropzone"
                            :class="isDragActiveClass"
                        >
                            <ct-block name="sw_media_upload_v2_preview">
                                <template v-if="variant === 'regular'">
                                    <ct-block name="sw_media_upload_v2_regular_preview_file">
                                        <ct-media-preview-v2
                                            v-if="showPreview && (source || preview)"
                                            class="ct-media-upload-v2__preview"
                                            :source="source || preview"
                                        />
                                    </ct-block>
                                    <ct-block name="sw_media_upload_v2_regular_preview_fallback">
                                        <template v-if="showPreview && (source || preview)"
                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                        >
                                        <div v-else class="ct-media-upload-v2__preview is--fallback">
                                            <mt-icon class="ct-media-upload-v2__fallback-icon" name="regular-image" />
                                        </div>
                                    </ct-block>
                                </template>

                                <template v-if="!showPreview && variant === 'regular'">
                                    <ct-block name="sw_media_upload_v2_regular_caption">
                                        <div class="ct-media-upload-v2__upload-caption">
                                            <mt-icon name="regular-cloud-upload" />
                                            {{ $t('global.ct-media-upload-v2.caption') }}
                                        </div>
                                    </ct-block>
                                </template>
                            </ct-block>

                            <ct-block name="sw_media_upload_v2_actions">
                                <div
                                    class="ct-media-upload-v2__actions"
                                    :class="{ 'has--source': source, 'is--small': variant === 'small' }"
                                >
                                    <div v-if="source" class="ct-media-upload-v2__file-info">
                                        <div class="ct-media-upload-v2__file-headline">
                                            {{ mediaNameFilter(source, source.name) }}
                                        </div>
                                        <mt-icon
                                            v-if="!disabled"
                                            class="ct-media-upload-v2__remove-icon"
                                            name="regular-times-xs"
                                            @click="onRemoveMediaItem"
                                        />
                                    </div>

                                    <template v-else>
                                        <ct-block name="sw_media_upload_v2_regular_actions_url">
                                            <ct-media-url-form
                                                v-if="isUrlUpload"
                                                class="ct-media-upload-v2__url-form"
                                                variant="inline"
                                                @media-url-form-submit="onUrlUpload"
                                            />
                                        </ct-block>

                                        <ct-block name="sw_media_upload_v2_regular_actions_add">
                                            <template v-if="isFileUpload">
                                                <ct-block name="sw_media_upload_v2_regular_media_sidebar_button">
                                                    <mt-button
                                                        v-if="hasOpenMediaButtonListener"
                                                        class="ct-media-upload-v2__button open-media-sidebar"
                                                        :class="{ 'is--small': variant === 'small' }"
                                                        variant="primary"
                                                        size="small"
                                                        :square="variant === 'small'"
                                                        :disabled="disabled"
                                                        @click="onClickOpenMediaSidebar"
                                                    >
                                                        <mt-icon
                                                            v-if="variant === 'small'"
                                                            name="regular-plus"
                                                            size="16px"
                                                        />
                                                        <template v-else>
                                                            {{ $t('global.ct-media-upload-v2.buttonOpenMedia') }}
                                                        </template>
                                                    </mt-button>
                                                </ct-block>

                                                <ct-block name="sw_media_upload_v2_regular_upload_button">
                                                    <mt-button
                                                        class="ct-media-upload-v2__button upload"
                                                        :class="{ 'is--small': variant === 'small' }"
                                                        size="small"
                                                        :disabled="disabled"
                                                        variant="secondary"
                                                        @click="onClickUpload"
                                                    >
                                                        {{ buttonFileUploadLabel }}
                                                    </mt-button>
                                                </ct-block>
                                            </template>
                                        </ct-block>
                                    </template>
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_media_upload_v2_file_input">
                <form ref="fileForm" class="ct-media-upload-v2__form">
                    <!-- eslint-disable-next-line vuejs-accessibility/form-control-has-label -->
                    <input
                        id="files"
                        ref="fileInput"
                        class="ct-media-upload-v2__file-input"
                        type="file"
                        :accept="extensionAccept ? '*/*' : fileAccept"
                        :multiple="multiSelect"
                        @change="onFileInputChange"
                    />
                </form>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-upload-v2.scss';
const { Context } = Contena;
const { fileReader } = Contena.Utils;
const { fileSize } = Contena.Utils.format;
const INPUT_TYPE_FILE_UPLOAD = 'file-upload';
const INPUT_TYPE_URL_UPLOAD = 'url-upload';

const props = defineProps({
    source: {
        type: [
            Object,
            String,
            File,
        ],
        required: false,
        default: null,
    },

    variant: {
        type: String,
        required: false,
        validValues: [
            'compact',
            'regular',
            'small',
        ],
        validator(value) {
            return [
                'compact',
                'regular',
                'small',
            ].includes(value);
        },
        default: 'regular',
    },

    uploadTag: {
        type: String,
        required: true,
    },

    allowMultiSelect: {
        type: Boolean,
        required: false,
        default: true,
    },

    addFilesOnMultiselect: {
        type: Boolean,
        required: false,
        default: false,
    },

    label: {
        type: String,
        required: false,
        default: null,
    },

    buttonLabel: {
        type: String,
        required: false,
        default: '',
    },

    defaultFolder: {
        type: String,
        required: false,
        validator(value) {
            return value.length > 0;
        },
        default: null,
    },

    targetFolderId: {
        type: String,
        required: false,
        default: null,
    },

    helpText: {
        type: String,
        required: false,
        default: null,
    },

    sourceContext: {
        type: Object,
        required: false,
        default: null,
    },

    fileAccept: {
        type: String,
        required: false,
        default: '*/*',
    },

    extensionAccept: {
        type: String,
        required: false,
        default: null,
    },

    extensionMimeTypesByExtension: {
        type: Object,
        required: false,
        default: () => ({}),
    },

    maxFileSize: {
        type: Number,
        required: false,
        default: null,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    privateFilesystem: {
        type: Boolean,
        required: false,
        default: false,
    },

    useFileData: {
        type: Boolean,
        required: false,
        default: false,
    },

    required: {
        type: Boolean,
        required: false,
        default: false,
    },

    onMediaUploadSidebarOpen: {
        type: Function,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'media-drop',
    'media-upload-sidebar-open',
    'media-upload-remove-image',
    'media-upload-add-file',
]);

import { ref, computed, inject, watch, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();

const dropzone = ref(null);
const fileInput = ref(null);
const fileForm = ref(null);

const repositoryFactory = inject('repositoryFactory');
const mediaService = inject('mediaService');
const mediaPresignedUploadService = inject('mediaPresignedUploadService');
const feature = inject('feature');
const fileValidationService = inject('fileValidationService');

const multiSelect = ref(props.allowMultiSelect);
const inputType = ref(INPUT_TYPE_FILE_UPLOAD);
const preview = ref(null);
const isDragActive = ref(false);
const defaultFolderId = ref(null);
const isUploadUrlFeatureEnabled = ref(Contena.Store.get('context').app.config?.settings?.enableUrlFeature ?? false);
const isLoading = ref(false);
const pendingUploadMediaIds = ref(new Set());

const defaultFolderRepository = computed(() => {
    return repositoryFactory.create('media_default_folder');
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media', '', {
        keepApiErrors: true,
    });
});
const showPreview = computed(() => {
    return !multiSelect.value;
});
const hasOpenMediaButtonListener = computed(() => {
    return !!props.onMediaUploadSidebarOpen;
});
const isDragActiveClass = computed(() => {
    return {
        'is--active': isDragActive.value,
        'is--multi': props.variant === 'regular' && !!multiSelect.value,
        'is--small': props.variant === 'small',
    };
});
const mediaFolderId = computed(() => {
    return defaultFolderId.value || props.targetFolderId;
});
const isUrlUpload = computed(() => {
    return inputType.value === INPUT_TYPE_URL_UPLOAD;
});
const isFileUpload = computed(() => {
    return inputType.value === INPUT_TYPE_FILE_UPLOAD;
});
const uploadUrlFeatureEnabled = computed(() => {
    return isUploadUrlFeatureEnabled.value;
});
const swFieldLabelClasses = computed(() => {
    return {
        'is--required': props.required,
    };
});
const buttonFileUploadLabel = computed(() => {
    if (props.buttonLabel === '') {
        return t('global.ct-media-upload-v2.buttonFileUpload');
    }

    return props.buttonLabel;
});
const mediaNameFilter = computed(() => {
    return Contena.Filter.getByName('mediaName');
});
const presignedUploadSupported = computed(() => {
    return Contena.Store.get('context').app.config?.settings?.presignedUploadSupported ?? false;
});

const createdComponent = async () => {
    mediaService.addListener(props.uploadTag, handleMediaServiceUploadEvent);

    if (mediaFolderId.value) {
        return;
    }

    if (props.defaultFolder) {
        isLoading.value = true;
        defaultFolderId.value = await getDefaultFolderId();
        isLoading.value = false;
    }
};
const mountedComponent = () => {
    if (dropzone.value) {
        [
            'dragover',
            'drop',
        ].forEach((event) => {
            window.addEventListener(event, stopEventPropagation, false);
        });
        dropzone.value.addEventListener('drop', onDrop);

        window.addEventListener('dragenter', onDragEnter);
        window.addEventListener('dragleave', onDragLeave);
    }
};
const beforeDestroyComponent = () => {
    cleanupOrphanedMedia();

    mediaService.removeByTag(props.uploadTag);
    mediaService.removeListener(props.uploadTag, handleMediaServiceUploadEvent);

    [
        'dragover',
        'drop',
    ].forEach((event) => {
        window.removeEventListener(event, stopEventPropagation, false);
    });
    if (dropzone.value) {
        dropzone.value.removeEventListener('drop', onDrop);
    }

    window.removeEventListener('dragenter', onDragEnter);
    window.removeEventListener('dragleave', onDragLeave);
};
function onDrop(event) {
    if (props.disabled) {
        return;
    }
    const newMediaFiles = Array.from(event.dataTransfer.files);
    isDragActive.value = false;
    if (newMediaFiles.length === 0) {
        return;
    }
    handleFileCheck(newMediaFiles);
}
const onDropMedia = (dragData) => {
    if (props.disabled) {
        return;
    }

    emit('media-drop', dragData.mediaItem);
};
function onDragEnter() {
    if (props.disabled) {
        return;
    }
    isDragActive.value = true;
}
function onDragLeave(event) {
    if (event.screenX === 0 && event.screenY === 0) {
        isDragActive.value = false;
        return;
    }
    const target = event.target;
    if (target.closest('.ct-media-upload-v2__dropzone')) {
        return;
    }
    isDragActive.value = false;
}
function stopEventPropagation(event) {
    event.preventDefault();
    event.stopPropagation();
}
const onClickUpload = () => {
    fileInput.value.click();
};
const useUrlUpload = () => {
    inputType.value = INPUT_TYPE_URL_UPLOAD;
};
const useFileUpload = () => {
    inputType.value = INPUT_TYPE_FILE_UPLOAD;
};
const onClickOpenMediaSidebar = () => {
    emit('media-upload-sidebar-open');
};
const onRemoveMediaItem = () => {
    if (props.disabled) {
        return;
    }

    preview.value = null;
    emit('media-upload-remove-image');
};
const onUrlUpload = async ({ url, fileExtension }) => {
    if (!multiSelect.value) {
        mediaService.removeByTag(props.uploadTag);
        preview.value = url;
    }

    let fileInfo;

    try {
        fileInfo = fileReader.getNameAndExtensionFromUrl(url);
    } catch (_error) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.ct-media-upload-v2.notification.invalidUrl.message'),
        });

        return;
    }

    if (fileExtension) {
        fileInfo.extension = fileExtension;
    }

    const targetEntity = getMediaEntityForUpload();

    await mediaRepository.value.save(targetEntity, Context.api);
    mediaService.addUpload(props.uploadTag, {
        src: url,
        targetId: targetEntity.id,
        isPrivate: targetEntity.private,
        ...fileInfo,
    });

    useFileUpload();
};
const onFileInputChange = () => {
    const newMediaFiles = Array.from(fileInput.value.files);

    if (!newMediaFiles.length) {
        return;
    }

    handleFileCheck(newMediaFiles);

    fileForm.value.reset();
};
const handleUpload = async (newMediaFiles) => {
    if (!multiSelect.value) {
        mediaService.removeByTag(props.uploadTag);
        newMediaFiles = [newMediaFiles.pop()];
        preview.value = newMediaFiles[0];
    } else {
        if (!preview.value) {
            preview.value = [];
        }

        if (props.addFilesOnMultiselect) {
            preview.value = [
                ...preview.value,
                ...newMediaFiles,
            ];
        } else {
            preview.value = newMediaFiles;
        }
    }

    if (presignedUploadSupported.value) {
        await handlePresignedUpload(newMediaFiles);
        return;
    }
    const syncEntities = [];

    const uploadData = newMediaFiles.map((fileHandle) => {
        const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
        const targetEntity = getMediaEntityForUpload();
        syncEntities.push(targetEntity);

        return {
            src: fileHandle,
            targetId: targetEntity.id,
            fileName,
            extension,
            isPrivate: targetEntity.private,
        };
    });

    await mediaRepository.value.sync(syncEntities, Context.api);

    syncEntities.forEach((entity) => {
        if (entity.id) {
            pendingUploadMediaIds.value.add(entity.id);
        }
    });

    await mediaService.addUploads(props.uploadTag, uploadData);
};
async function handlePresignedUpload(files) {
    await mediaPresignedUploadService.runUploads(
        props.uploadTag,
        files,
        {
            mediaFolderId: mediaFolderId.value,
            isPrivate: props.privateFilesystem,
        },
        {
            getListeners: (tag) => mediaService.getListenerForTag(tag),
            createEvent: (action, tag, payload) => mediaService._createUploadEvent(action, tag, payload),
        },
    );
}
function getMediaEntityForUpload() {
    const mediaItem = mediaRepository.value.create();
    mediaItem.mediaFolderId = mediaFolderId.value;
    mediaItem.private = props.privateFilesystem;
    return mediaItem;
}
function cleanupOrphanedMedia() {
    if (pendingUploadMediaIds.value.size === 0) {
        return;
    }
    const pendingIds = Array.from(pendingUploadMediaIds.value);
    pendingUploadMediaIds.value.clear();
    pendingIds.forEach((mediaId) => {
        Promise.resolve()
            .then(() => mediaRepository.value.get(mediaId, Context.api))
            .then((media) => {
                if (media && !media.hasFile) {
                    return mediaRepository.value.delete(mediaId, Context.api);
                }
                return null;
            })
            .catch((error) => {
                Contena.Utils.debug.warn('ct-media-upload-v2', 'Failed to clean up orphaned media', mediaId, error);
            });
    });
}
function getDefaultFolderId() {
    return mediaService.getDefaultFolderId(props.defaultFolder);
}
function handleMediaServiceUploadEvent({ action, payload }) {
    // Keep the id on failure so the orphaned entity is still cleaned up on teardown.
    if (action === 'media-upload-finish') {
        pendingUploadMediaIds.value.delete(payload.targetId);
    }
    if (action === 'media-upload-fail') {
        onRemoveMediaItem();
    }
}
const checkFileSize = (file) => {
    if (props.maxFileSize === null || file.size <= props.maxFileSize || file.fileSize <= props.maxFileSize) {
        return true;
    }

    createNotificationError({
        message: t(
            'global.ct-media-upload-v2.notification.invalidFileSize.message',
            {
                name: file.name || file.fileName,
                limit: fileSize(props.maxFileSize),
            },
            0,
        ),
    });
    return false;
};
const checkFileType = (file) => {
    // Set file type and file name if file is a media entity item
    if (!file?.type && file.id) {
        file.type = file.mimeType;
    }

    if (!file?.name && file.id) {
        file.name = file.fileName;
    }

    const isValidFile = () => {
        if (props.extensionAccept) {
            return fileValidationService.checkByExtension(
                file,
                props.extensionAccept,
                null,
                props.extensionMimeTypesByExtension,
            );
        }

        if (props.fileAccept) {
            return fileValidationService.checkByType(file, props.fileAccept);
        }

        return false;
    };

    if (isValidFile()) {
        return true;
    }

    createNotificationError({
        message: t(
            'global.ct-media-upload-v2.notification.invalidFileType.message',
            {
                name: file.name,
                supportedTypes: props.extensionAccept || props.fileAccept,
            },
            0,
        ),
    });

    return false;
};
function handleFileCheck(files) {
    const checkedFiles = files.filter((file) => {
        return checkFileSize(file) && checkFileType(file);
    });
    if (props.useFileData) {
        preview.value = !multiSelect.value ? checkedFiles[0] : null;
        emit('media-upload-add-file', checkedFiles);
    } else {
        void handleUpload(checkedFiles);
    }
}

watch(
    () => props.defaultFolder,
    async () => {
        defaultFolderId.value = await getDefaultFolderId();
    },
);
watch(
    () => props.disabled,
    (newValue) => {
        if (newValue) {
            isDragActive.value = false;
        }
    },
);

void createdComponent();

onMounted(() => {
    mountedComponent();
});
onBeforeUnmount(() => {
    beforeDestroyComponent();
});

swDefinePublic({
    repositoryFactory,
    mediaService,
    mediaPresignedUploadService,
    feature,
    fileValidationService,
    multiSelect,
    inputType,
    preview,
    isDragActive,
    defaultFolderId,
    isUploadUrlFeatureEnabled,
    isLoading,
    pendingUploadMediaIds,
    defaultFolderRepository,
    mediaRepository,
    showPreview,
    hasOpenMediaButtonListener,
    isDragActiveClass,
    mediaFolderId,
    isUrlUpload,
    isFileUpload,
    uploadUrlFeatureEnabled,
    swFieldLabelClasses,
    buttonFileUploadLabel,
    mediaNameFilter,
    presignedUploadSupported,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    onDrop,
    onDropMedia,
    onDragEnter,
    onDragLeave,
    stopEventPropagation,
    onClickUpload,
    useUrlUpload,
    useFileUpload,
    onClickOpenMediaSidebar,
    onRemoveMediaItem,
    onUrlUpload,
    onFileInputChange,
    handleUpload,
    handlePresignedUpload,
    getMediaEntityForUpload,
    cleanupOrphanedMedia,
    getDefaultFolderId,
    handleMediaServiceUploadEvent,
    checkFileSize,
    checkFileType,
    handleFileCheck,
});

defineExpose({
    repositoryFactory,
    mediaService,
    mediaPresignedUploadService,
    feature,
    fileValidationService,
    multiSelect,
    inputType,
    preview,
    isDragActive,
    defaultFolderId,
    isUploadUrlFeatureEnabled,
    isLoading,
    pendingUploadMediaIds,
    defaultFolderRepository,
    mediaRepository,
    showPreview,
    hasOpenMediaButtonListener,
    isDragActiveClass,
    mediaFolderId,
    isUrlUpload,
    isFileUpload,
    uploadUrlFeatureEnabled,
    swFieldLabelClasses,
    buttonFileUploadLabel,
    mediaNameFilter,
    presignedUploadSupported,
    createdComponent,
    mountedComponent,
    beforeDestroyComponent,
    onDrop,
    onDropMedia,
    onDragEnter,
    onDragLeave,
    stopEventPropagation,
    onClickUpload,
    useUrlUpload,
    useFileUpload,
    onClickOpenMediaSidebar,
    onRemoveMediaItem,
    onUrlUpload,
    onFileInputChange,
    handleUpload,
    handlePresignedUpload,
    getMediaEntityForUpload,
    cleanupOrphanedMedia,
    getDefaultFolderId,
    handleMediaServiceUploadEvent,
    checkFileSize,
    checkFileType,
    handleFileCheck,
});
</script>
