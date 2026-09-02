<template>
    <ct-block name="ct_file_input">
        <div class="ct-file-input">
            <ct-block name="ct_file_input_regular">
                <ct-block name="ct_file_input_regular_header">
                    <div class="ct-file-input__header">
                        <ct-block name="ct_file_input_regular_header_label">
                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                            <label v-if="label" :for="id" class="ct-file-input__label">
                                {{ label }}
                            </label>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_file_input_regular_drop_zone">
                    <div ref="dropzone" class="ct-file-input__dropzone" :class="isDragActiveClass">
                        <ct-block name="ct_file_input_caption">
                            <div v-if="selectedFile === null" class="ct-file-input__upload-caption">
                                <mt-icon name="regular-cloud-upload" size="18px" />

                                <slot name="caption-label">
                                    {{ $t('global.ct-file-input.caption') }}
                                </slot>
                            </div>
                        </ct-block>

                        <ct-block name="ct_file_input_actions">
                            <div class="ct-file-input__actions" :class="{ 'has--source': selectedFile }">
                                <div v-if="selectedFile" class="ct-file-input__file-info">
                                    <span class="ct-file-input__file-headline">
                                        {{ selectedFile.name }}
                                    </span>

                                    <mt-icon
                                        class="ct-file-input__remove-icon"
                                        name="regular-times-xs"
                                        size="12px"
                                        @click="onRemoveIconClick"
                                    />
                                </div>

                                <ct-block name="ct_file_input_regular_choose_button">
                                    <mt-button
                                        class="ct-file-input__button"
                                        size="small"
                                        :disabled="disabled || undefined"
                                        variant="secondary"
                                        @click="onChooseButtonClick"
                                    >
                                        {{ $t('global.ct-file-input.buttonChoose') }}
                                    </mt-button>
                                </ct-block>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>
            </ct-block>

            <ct-block name="ct_file_input_hidden_form">
                <form ref="fileForm" class="ct-file-input__file-form">
                    <!-- eslint-disable-next-line vuejs-accessibility/form-control-has-label -->
                    <input
                        :id="id"
                        ref="fileInput"
                        class="ct-file-input__file-input"
                        type="file"
                        @change="onFileInputChange"
                    />
                </form>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-file-input.scss';
const { fileSize } = Contena.Utils.format;
const utils = Contena.Utils;

const props = defineProps({
    maxFileSize: {
        type: Number,
        required: false,
        default: null,
    },

    allowedMimeTypes: {
        type: Array,
        required: false,
        default: null,
    },

    allowedFileExtensions: {
        type: Array,
        required: false,
        default: null,
    },

    label: {
        type: String,
        required: false,
        default: null,
    },

    value: {
        required: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits(['update:value']);

import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();

const dropzone = ref(null);
const fileInput = ref(null);
const fileForm = ref(null);

const feature = inject('feature');

const selectedFile = ref(null);
const utilsId = ref(utils.createId());
const isDragActive = ref(false);

const id = computed(() => {
    return `ct-file-input--${utilsId.value}`;
});
const isDragActiveClass = computed(() => {
    return {
        'is--active': isDragActive.value,
    };
});

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
const beforeUnmountComponent = () => {
    if (dropzone.value) {
        [
            'dragover',
            'drop',
        ].forEach((event) => {
            window.removeEventListener(event, stopEventPropagation, false);
        });
        dropzone.value.removeEventListener('drop', onDrop);

        window.removeEventListener('dragenter', onDragEnter);
        window.removeEventListener('dragleave', onDragLeave);
    }
};
const onChooseButtonClick = () => {
    fileInput.value.click();
};
const onRemoveIconClick = () => {
    setSelectedFile(null);
};
const onFileInputChange = () => {
    const newFiles = Array.from(fileInput.value.files);

    if (newFiles.length) {
        const newFile = newFiles[0];
        if (checkFileSize(newFile) && checkFileExtension(newFile) && checkFileType(newFile)) {
            setSelectedFile(newFile);
        }
    }
    fileForm.value.reset();
};
function setSelectedFile(newFile) {
    selectedFile.value = newFile;
    emit('update:value', selectedFile.value);
}
function checkFileSize(file) {
    if (props.maxFileSize === null || file.size <= props.maxFileSize) {
        return true;
    }
    createNotificationError({
        title: t('global.default.error'),
        message: t('global.ct-file-input.notification.invalidFileSize.message', {
            name: file.name,
            limit: fileSize(props.maxFileSize),
        }),
    });
    return false;
}
function checkFileType(file) {
    if (!props.allowedMimeTypes || !props.allowedMimeTypes.length || props.allowedMimeTypes.indexOf(file.type) >= 0) {
        return true;
    }
    createNotificationError({
        title: t('global.default.error'),
        message: t('global.ct-file-input.notification.invalidFileType.message', {
            name: file.name,
            supportedTypes: props.allowedMimeTypes.join(', '),
        }),
    });
    return false;
}
function checkFileExtension(file) {
    const extension = file.name.toLowerCase().split('.').pop();
    if (
        !props.allowedFileExtensions ||
        !props.allowedFileExtensions.length ||
        props.allowedFileExtensions.includes(extension)
    ) {
        return true;
    }
    createNotificationError({
        title: t('global.default.error'),
        message: t('global.ct-file-input.notification.invalidFileExtension.message', {
            name: file.name,
            supportedExtensions: props.allowedFileExtensions.join(', '),
        }),
    });
    return false;
}
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
    if (target.closest('.ct-file-input__dropzone')) {
        return;
    }
    isDragActive.value = false;
}
function stopEventPropagation(event) {
    event.preventDefault();
    event.stopPropagation();
}
function onDrop(event) {
    if (props.disabled) {
        return;
    }
    const newFiles = Array.from(event.dataTransfer.files);
    isDragActive.value = false;
    if (newFiles.length === 0) {
        return;
    }
    const newFile = newFiles[0];
    if (checkFileSize(newFile) && checkFileExtension(newFile) && checkFileType(newFile)) {
        setSelectedFile(newFile);
    }
    fileForm.value.reset();
}

onMounted(() => {
    mountedComponent();
});
onBeforeUnmount(() => {
    beforeUnmountComponent();
});

ctDefinePublic({
    feature,
    selectedFile,
    utilsId,
    isDragActive,
    id,
    isDragActiveClass,
    mountedComponent,
    beforeUnmountComponent,
    onChooseButtonClick,
    onRemoveIconClick,
    onFileInputChange,
    setSelectedFile,
    checkFileSize,
    checkFileType,
    checkFileExtension,
    onDragEnter,
    onDragLeave,
    stopEventPropagation,
    onDrop,
});

defineExpose({
    feature,
    selectedFile,
    utilsId,
    isDragActive,
    id,
    isDragActiveClass,
    mountedComponent,
    beforeUnmountComponent,
    onChooseButtonClick,
    onRemoveIconClick,
    onFileInputChange,
    setSelectedFile,
    checkFileSize,
    checkFileType,
    checkFileExtension,
    onDragEnter,
    onDragLeave,
    stopEventPropagation,
    onDrop,
});
</script>
