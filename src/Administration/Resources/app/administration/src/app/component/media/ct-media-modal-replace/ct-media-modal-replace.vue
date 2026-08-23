<template>
    <ct-block name="sw_media_modal_replace">
        <ct-modal
            class="ct-media-modal-replace"
            size="420px"
            :title="$t('global.ct-media-modal-replace.titleModal')"
            @modal-close="emitCloseReplaceModal"
        >
            <ct-upload-listener :upload-tag="itemToReplace.id" @media-upload-add="onNewUpload" />

            <ct-media-replace
                class="ct-media-modal-replace__upload"
                :item-to-replace="itemToReplace"
                :upload-tag="itemToReplace.id"
                variant="regular"
            />

            <mt-banner v-if="newFileExtension" class="ct-media-modal-replace__file-extension-warning" variant="attention">
                {{ $t('global.ct-media-modal-replace.warningFileExtension', { extension: newFileExtension }, 0) }}
            </mt-banner>

            <template #modal-footer>
                <ct-block name="sw_media_modal_replace_modal_footer">
                    <ct-block name="sw_media_modal_replace_cancel_button">
                        <mt-button size="small" variant="secondary" @click="emitCloseReplaceModal">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_media_modal_replace_replace_button">
                        <mt-button
                            class="ct-media-replace__replace-media-action"
                            size="small"
                            variant="primary"
                            :disabled="!isUploadDataSet"
                            @click="replaceMediaItem"
                        >
                            {{ $t('global.ct-media-modal-replace.buttonReplace') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-media-modal-replace.scss';

const props = defineProps({
    itemToReplace: {
        type: Object,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'media-replace-modal-close',
    'media-replace-modal-item-replaced',
]);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();
const itemToReplace = computed(() => props.itemToReplace);

const mediaService = inject('mediaService');
const mediaPresignedUploadService = inject('mediaPresignedUploadService');
const repositoryFactory = inject('repositoryFactory');

const isUploadDataSet = ref(false);
const newFileExtension = ref('');
const pendingPresignedFile = ref(null);

const presignedSupported = computed(() => {
    return Contena.Store.get('context').app.config?.settings?.presignedUploadSupported ?? false;
});

const onNewUpload = ({ data }) => {
    isUploadDataSet.value = true;

    // overwrite file name randomly to avoid conflicts on upload before renaming
    // e.g. you want to replace image.png with contena.png but contena.png already exists
    data[0].fileName = Contena.Utils.createId();

    const uploadedFileExtension = data[0].extension;
    const oldFileExtension = itemToReplace.value.fileExtension;

    if (uploadedFileExtension !== oldFileExtension) {
        newFileExtension.value = uploadedFileExtension;
    }

    if (presignedSupported.value && data[0].src instanceof File) {
        pendingPresignedFile.value = data[0].src;
    }
};
const emitCloseReplaceModal = () => {
    emit('media-replace-modal-close');
};
const replaceMediaItem = async () => {
    itemToReplace.value.isLoading = true;
    const previousName = itemToReplace.value.fileName;

    try {
        if (pendingPresignedFile.value) {
            await runPresignedReplace(pendingPresignedFile.value);
        } else {
            await mediaService.runUploads(itemToReplace.value.id);
        }

        await mediaService.renameMedia(itemToReplace.value.id, previousName);

        emit('media-replace-modal-item-replaced');
    } catch {
        createNotificationError({
            message: t('global.default.notification.unspecifiedSaveErrorMessage'),
        });
    } finally {
        itemToReplace.value.isLoading = false;
    }
};
async function runPresignedReplace(fileHandle) {
    const { fileReader } = Contena.Utils;
    const { fileName, extension } = fileReader.getNameAndExtensionFromFile(fileHandle);
    const mimeType = fileHandle.type || 'application/octet-stream';
    const [
        result,
        dimensions,
    ] = await Promise.all([
        mediaPresignedUploadService.prepareUpload({
            fileName,
            extension,
            mimeType,
            mediaId: itemToReplace.value.id,
        }),
        mediaPresignedUploadService.getImageDimensions(fileHandle),
    ]);
    await mediaPresignedUploadService.uploadToPresignedUrl(result.url, fileHandle, mimeType);
    await mediaPresignedUploadService.finalizeUpload(itemToReplace.value.id, {
        fileName,
        extension,
        mimeType,
        path: result.path,
        width: dimensions?.width ?? null,
        height: dimensions?.height ?? null,
    });
}

swDefinePublic({
    mediaService,
    mediaPresignedUploadService,
    repositoryFactory,
    isUploadDataSet,
    newFileExtension,
    pendingPresignedFile,
    presignedSupported,
    onNewUpload,
    emitCloseReplaceModal,
    replaceMediaItem,
    runPresignedReplace,
});

defineExpose({
    mediaService,
    mediaPresignedUploadService,
    repositoryFactory,
    isUploadDataSet,
    newFileExtension,
    pendingPresignedFile,
    presignedSupported,
    onNewUpload,
    emitCloseReplaceModal,
    replaceMediaItem,
    runPresignedReplace,
});
</script>
