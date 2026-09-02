<template>
    <ct-block name="ct_duplicated_media_v2">
        <ct-modal
            v-if="showModal"
            class="ct-duplicated-media-v2"
            :title="translate('global.ct-duplicated-media-v2.titleModal')"
            @modal-close="skipAll"
        >
            <ct-block name="ct_duplicated_media_v2_body">
                <ct-block name="ct_duplicated_media_v2_body_description">
                    <p class="ct-duplicated-media-v2__description">
                        {{
                            translate(
                                'global.ct-duplicated-media-v2.description',
                                { fileName: `${currentTask.fileName}.${currentTask.extension}` },
                                1,
                            )
                        }}
                    </p>
                </ct-block>

                <ct-block name="ct_duplicated_media_v2_body_preview">
                    <ct-container
                        class="ct-duplicated-media-v2__preview"
                        rows="20px 1fr"
                        columns="1fr 100px 1fr"
                        align="center"
                    >
                        <ct-block name="ct_duplicated_media_v2_body_preview_label_old_file">
                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                            <label class="ct-duplicated-media-v2__preview_label">
                                {{ translate('global.ct-duplicated-media-v2.labelNewFile') }}
                            </label>
                        </ct-block>

                        <div class="ct-duplicated-media-v2__spacer"></div>

                        <ct-block name="ct_duplicated_media_v2_body_preview_label_new_file">
                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                            <label class="ct-duplicated-media-v2__preview_label">
                                {{ translate('global.ct-duplicated-media-v2.labelOldFile') }}
                            </label>
                        </ct-block>

                        <ct-block name="ct_duplicated_media_v2_body_preview_new_media">
                            <div class="ct-duplicated-media-v2__target-upload">
                                <ct-block name="ct_media_duplicated_media_target_preview">
                                    <div class="ct-duplicated-media-v2__target-preview">
                                        <ct-media-preview-v2 :source="currentTask.src" />
                                    </div>
                                </ct-block>

                                <ct-block name="ct_media_duplicated_media_target_name">
                                    <span
                                        v-if="selectedOption !== 'Rename'"
                                        key="ct-duplicated-media-v2__target-label-fileName"
                                        class="ct-duplicated-media-v2__target-label"
                                    >
                                        {{ `${currentTask.fileName}.${currentTask.extension}` }}
                                    </span>
                                    <span
                                        v-else
                                        key="ct-duplicated-media-v2__target-label-suggestedName"
                                        class="ct-duplicated-media-v2__target-label"
                                    >
                                        {{ `${suggestedName}.${currentTask.extension}` }}
                                    </span>
                                </ct-block>

                                <ct-block name="ct_media_duplicated_media_target_details">
                                    <span class="ct-duplicated-media-v2__target-details">{{ currentTaskDetails }}</span>
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="ct_duplicated_media_v2_body_preview_separator">
                            <mt-icon class="ct-duplicated-media-v2__preview-separator" name="regular-long-arrow-right" />
                        </ct-block>

                        <ct-block name="ct_duplicated_media_v2_body_preview_old_media">
                            <ct-media-media-item
                                v-if="existingMedia"
                                :item="existingMedia"
                                :selected="false"
                                :show-selection-indicator="false"
                                :is-list="true"
                                :editable="false"
                                :show-context-menu-button="false"
                            />
                        </ct-block>
                    </ct-container>
                </ct-block>

                <ct-block name="ct_duplicated_media_v2_body_options">
                    <mt-radio-group-root v-model="selectedOption" class="ct-duplicated-media-v2__options">
                        <mt-radio-group-list>
                            <mt-radio-group-item
                                v-for="option in options"
                                :id="`duplicate-media-option-${option.value}`"
                                :key="option.value"
                                :value="option.value"
                                :label="option.name"
                            />
                        </mt-radio-group-list>
                    </mt-radio-group-root>
                </ct-block>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_duplicated_media_v2_footer">
                    <ct-block name="ct_duplicated_media_v2_save_selection">
                        <mt-checkbox
                            v-if="!isLoading && hasAdditionalErrors"
                            v-model:checked="shouldSaveSelection"
                            class="ct-duplicated-media-v2__additional-error-count"
                            :label="
                                translate('global.ct-duplicated-media-v2.labelSaveSelection', {
                                    count: additionalErrorCount,
                                })
                            "
                        />
                    </ct-block>

                    <ct-block name="ct_duplicated_media_v2_cancel_button">
                        <mt-button size="small" :disabled="isLoading" variant="secondary" @click="skipCurrentFile">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_duplicated_media_v2_upload_button">
                        <mt-button
                            class="ct-duplicated-media-v2__upload"
                            :disabled="isLoading"
                            size="small"
                            variant="primary"
                            @click="solveDuplicate"
                        >
                            {{ buttonLabel }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-duplicated-media-v2.scss';
const { Context, Filter } = Contena;
const { Criteria } = Contena.Data;
const LOCAL_STORAGE_KEY_OPTION = 'ct-duplicate-media-resolve-option';
const LOCAL_STORAGE_SAVE_SELECTION = 'ct-duplicate-media-resolve-save-selection';

defineProps({});

import { ref, computed, inject, watch, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const translate = t;
const repositoryFactory = inject('repositoryFactory');
const mediaService = inject('mediaService');
const mediaPresignedUploadService = inject('mediaPresignedUploadService');

const isLoading = ref(false);
const shouldSaveSelection = ref(false);
const selectedOption = ref('Replace');
const defaultOption = ref('Replace');
const suggestedName = ref('');
const existingMedia = ref(null);
const targetEntity = ref(null);
const failedUploadTasks = ref([]);
const postponedFailedUploads = ref([]);

const presignedSupported = computed(() => {
    return Contena.Store.get('context').app.config?.settings?.presignedUploadSupported ?? false;
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const additionalErrorCount = computed(() => {
    return failedUploadTasks.value.length - 1;
});
const hasAdditionalErrors = computed(() => {
    return additionalErrorCount.value > 0;
});
const currentTask = computed(() => {
    return failedUploadTasks.value[0];
});
const buttonLabel = computed(() => {
    return t(`global.ct-duplicated-media-v2.button${selectedOption.value}`);
});
const dateFilter = computed(() => {
    return Filter.getByName('date');
});
const fileSizeFilter = computed(() => {
    return Filter.getByName('fileSize');
});
const currentTaskDetails = computed(() => {
    if (!currentTask.value) {
        return '';
    }
    const metadata = [
        dateFilter.value(new Date(), { month: 'long' }),
    ];

    if (currentTask.value.src instanceof File) {
        metadata.push(fileSizeFilter.value(currentTask.value.src.size));
    }

    return metadata.join(', ');
});
const showModal = computed(() => {
    return failedUploadTasks.value.length > 0 && !isWorkingOnMultipleTasks.value;
});
const isWorkingOnMultipleTasks = computed(() => {
    return isLoading.value && shouldSaveSelection.value;
});
const options = computed(() => {
    return [
        {
            value: 'Replace',
            name: t('global.ct-duplicated-media-v2.labelOptionReplace'),
        },
        {
            value: 'Rename',
            name: t('global.ct-duplicated-media-v2.labelOptionRename'),
        },
        {
            value: 'Keep',
            name: t('global.ct-duplicated-media-v2.labelOptionKeep'),
        },
        {
            value: 'Skip',
            name: t('global.ct-duplicated-media-v2.labelOptionSkip'),
        },
    ];
});

const loadDefaultOption = () => {
    shouldSaveSelection.value = localStorage.getItem(LOCAL_STORAGE_SAVE_SELECTION) === 'true';

    if (shouldSaveSelection.value) {
        defaultOption.value = localStorage.getItem(LOCAL_STORAGE_KEY_OPTION) || 'Replace';
    }

    selectedOption.value = defaultOption.value;
};
const saveDefaultOption = () => {
    localStorage.setItem(LOCAL_STORAGE_SAVE_SELECTION, String(shouldSaveSelection.value));

    if (shouldSaveSelection.value) {
        localStorage.setItem(LOCAL_STORAGE_KEY_OPTION, defaultOption.value);
    }
};
const createdComponent = () => {
    loadDefaultOption();
    void updatePreviewData();
    mediaService.addDefaultListener(handleMediaServiceUploadEvent);
};
const beforeDestroyComponent = () => {
    mediaService.removeDefaultListener(handleMediaServiceUploadEvent);
};
function handleMediaServiceUploadEvent({ action, payload }) {
    if (action !== 'media-upload-fail') {
        return;
    }
    if (!isDuplicatedNameError(payload.error)) {
        return;
    }
    if (isLoading.value) {
        postponedFailedUploads.value.push(payload);
        return;
    }
    failedUploadTasks.value.push(payload);
}
function isDuplicatedNameError(error) {
    return error?.response?.data?.errors?.some((err) => {
        return err.code === 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';
    });
}
async function updatePreviewData() {
    if (!currentTask.value) {
        existingMedia.value = null;
        suggestedName.value = '';
        return;
    }
    const criteria = new Criteria(1, 1).addFilter(
        Criteria.multi('AND', [
            Criteria.equals('fileName', currentTask.value.fileName),
            Criteria.equals('fileExtension', currentTask.value.extension),
            Criteria.equals('private', currentTask.value.isPrivate),
        ]),
    );
    const searchResult = await mediaRepository.value.search(criteria, Context.api);
    if (searchResult?.[0]) {
        existingMedia.value = searchResult[0];
    }
    const provided = await mediaService.provideName(currentTask.value.fileName, currentTask.value.extension);
    suggestedName.value = provided.fileName;
}
const solveDuplicate = async () => {
    if (!currentTask.value) {
        isLoading.value = false;
        return;
    }

    isLoading.value = true;

    switch (selectedOption.value) {
        case 'Rename':
            await renameFile(currentTask.value);
            break;
        case 'Replace':
            await replaceFile(currentTask.value);
            break;
        case 'Keep':
            await keepFile(currentTask.value);
            break;
        case 'Skip':
        default:
            await skipFile(currentTask.value);
            break;
    }

    failedUploadTasks.value.splice(0, 1);

    if (!currentTask.value || !isWorkingOnMultipleTasks.value) {
        isLoading.value = false;
    } else {
        await solveDuplicate();
    }
};
async function renameFile(uploadTask) {
    const newTask = {
        ...uploadTask,
    };
    const { fileName } = await mediaService.provideName(uploadTask.fileName, uploadTask.extension);
    newTask.fileName = fileName;
    if (presignedSupported.value && uploadTask.src instanceof File) {
        const mediaId = await presignedUpload(newTask, newTask.targetId);
        emitUploadFinished(newTask.uploadTag, mediaId);
        return;
    }
    mediaService.addUpload(newTask.uploadTag, newTask);
    await mediaService.runUploads(newTask.uploadTag);
}
const skipAll = async () => {
    isLoading.value = true;

    await skipFile(currentTask.value);
    failedUploadTasks.value.splice(0, 1);

    if (!currentTask.value) {
        isLoading.value = false;
    } else {
        await skipAll();
    }
};
const skipCurrentFile = async () => {
    isLoading.value = true;
    await skipFile(currentTask.value);

    failedUploadTasks.value.splice(0, 1);
    isLoading.value = false;
};
async function skipFile(uploadTask) {
    const oldTarget = await mediaRepository.value.get(uploadTask.targetId, Context.api);
    if (!oldTarget.hasFile) {
        await mediaRepository.value.delete(oldTarget.id, Context.api);
    }
    mediaService.cancelUpload(uploadTask.uploadTag, uploadTask);
}
async function replaceFile(uploadTask) {
    const criteria = new Criteria(1, 1).addFilter(
        Criteria.multi('AND', [
            Criteria.equals('fileName', uploadTask.fileName),
            Criteria.equals('fileExtension', uploadTask.extension),
            Criteria.equals('private', uploadTask.isPrivate),
        ]),
    );
    const searchResult = await mediaRepository.value.search(criteria, Context.api);
    const newTarget = searchResult[0];
    const oldTargetId = uploadTask.targetId;
    if (presignedSupported.value && uploadTask.src instanceof File) {
        try {
            const mediaId = await presignedUpload(uploadTask, newTarget.id);
            const oldTarget = await mediaRepository.value.get(oldTargetId, Context.api);
            if (oldTarget && !oldTarget.hasFile) {
                await mediaRepository.value.delete(oldTargetId, Context.api);
            }
            emitUploadFinished(uploadTask.uploadTag, mediaId, mediaId !== oldTargetId ? oldTargetId : null);
        } catch (e) {
            const oldTarget = await mediaRepository.value.get(oldTargetId, Context.api);
            if (oldTarget && !oldTarget.hasFile) {
                await mediaRepository.value.delete(oldTargetId, Context.api);
            }
            throw e;
        }
        return;
    }
    uploadTask.targetId = newTarget.id;
    mediaService.addUpload(uploadTask.uploadTag, uploadTask);
    await mediaService.runUploads(uploadTask.uploadTag);
    const oldTarget = await mediaRepository.value.get(oldTargetId, Context.api);
    if (!oldTarget.hasFile) {
        await mediaRepository.value.delete(oldTargetId, Context.api);
    }
    await mediaRepository.value.get(uploadTask.targetId, Context.api);
}
async function presignedUpload(uploadTask, mediaId) {
    const mimeType = uploadTask.src.type || 'application/octet-stream';
    const [
        result,
        dimensions,
    ] = await Promise.all([
        mediaPresignedUploadService.prepareUpload({
            fileName: uploadTask.fileName,
            extension: uploadTask.extension,
            mimeType,
            mediaId,
        }),
        mediaPresignedUploadService.getImageDimensions(uploadTask.src),
    ]);
    await mediaPresignedUploadService.uploadToPresignedUrl(result.url, uploadTask.src, mimeType);
    await mediaPresignedUploadService.finalizeUpload(result.mediaId, {
        fileName: uploadTask.fileName,
        extension: uploadTask.extension,
        mimeType,
        path: result.path,
        width: dimensions?.width ?? null,
        height: dimensions?.height ?? null,
    });
    return result.mediaId;
}
function emitUploadFinished(uploadTag, targetId, originalTargetId = null) {
    mediaService.getListenerForTag(uploadTag).forEach((listener) => {
        listener(
            mediaService._createUploadEvent('media-upload-finish', uploadTag, {
                targetId,
                originalTargetId,
                successAmount: 1,
                failureAmount: 0,
                totalAmount: 1,
            }),
        );
    });
}
async function keepFile(uploadTask) {
    const originalTargetId = uploadTask.targetId;
    const oldTarget = await mediaRepository.value.get(uploadTask.targetId, Context.api);
    if (!oldTarget.hasFile) {
        await mediaRepository.value.delete(oldTarget.id, Context.api);
    }
    const criteria = new Criteria(1, 1).addFilter(
        Criteria.multi('AND', [
            Criteria.equals('fileName', uploadTask.fileName),
            Criteria.equals('fileExtension', uploadTask.extension),
            Criteria.equals('private', uploadTask.isPrivate),
        ]),
    );
    const searchResult = await mediaRepository.value.search(criteria, Context.api);
    const newTarget = searchResult[0];
    uploadTask.targetId = newTarget.id;
    uploadTask.originalTargetId = originalTargetId;
    mediaService.keepFile(uploadTask.uploadTag, uploadTask);
}

watch(
    () => showModal.value,
    (isShown) => {
        if (isShown) {
            loadDefaultOption();
            return;
        }

        saveDefaultOption();
    },
);
watch(
    () => currentTask.value,
    () => {
        void updatePreviewData();
    },
);
watch(
    () => isLoading.value,
    (newVal) => {
        if (newVal) {
            return;
        }

        failedUploadTasks.value.push(...postponedFailedUploads.value.splice(0, postponedFailedUploads.value.length));
    },
);

onBeforeUnmount(() => {
    beforeDestroyComponent();
});

createdComponent();

ctDefinePublic({
    repositoryFactory,
    mediaService,
    mediaPresignedUploadService,
    isLoading,
    shouldSaveSelection,
    selectedOption,
    defaultOption,
    suggestedName,
    existingMedia,
    targetEntity,
    failedUploadTasks,
    postponedFailedUploads,
    presignedSupported,
    mediaRepository,
    additionalErrorCount,
    hasAdditionalErrors,
    currentTask,
    buttonLabel,
    dateFilter,
    fileSizeFilter,
    currentTaskDetails,
    showModal,
    isWorkingOnMultipleTasks,
    options,
    createdComponent,
    loadDefaultOption,
    saveDefaultOption,
    beforeDestroyComponent,
    handleMediaServiceUploadEvent,
    isDuplicatedNameError,
    updatePreviewData,
    solveDuplicate,
    renameFile,
    skipAll,
    skipCurrentFile,
    skipFile,
    replaceFile,
    presignedUpload,
    emitUploadFinished,
    keepFile,
});

defineExpose({
    repositoryFactory,
    mediaService,
    mediaPresignedUploadService,
    isLoading,
    shouldSaveSelection,
    selectedOption,
    defaultOption,
    suggestedName,
    existingMedia,
    targetEntity,
    failedUploadTasks,
    postponedFailedUploads,
    presignedSupported,
    mediaRepository,
    additionalErrorCount,
    hasAdditionalErrors,
    currentTask,
    buttonLabel,
    dateFilter,
    fileSizeFilter,
    currentTaskDetails,
    showModal,
    isWorkingOnMultipleTasks,
    options,
    createdComponent,
    loadDefaultOption,
    saveDefaultOption,
    beforeDestroyComponent,
    handleMediaServiceUploadEvent,
    isDuplicatedNameError,
    updatePreviewData,
    solveDuplicate,
    renameFile,
    skipAll,
    skipCurrentFile,
    skipFile,
    replaceFile,
    presignedUpload,
    emitUploadFinished,
    keepFile,
});
</script>
