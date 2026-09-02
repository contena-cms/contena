<template>
    <ct-block name="ct_media_upload_v2">
        <div class="ct-media-upload-v2 ct-media-compact-upload-v2">
            <ct-block name="ct_media_upload_v2_compact">
                <ct-block name="ct_media_upload_v2_compact_label">
                    <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                    <label v-if="label" class="ct-media-compact-upload-v2__label">
                        {{ label }}
                    </label>
                </ct-block>

                <div v-if="variant == 'compact'" class="ct-media-upload-v2__content">
                    <ct-button-group split-button>
                        <ct-block name="ct_media_upload_v2_compact_button_file_upload">
                            <mt-button
                                :disabled="disabled"
                                class="ct-media-upload-v2__button-compact-upload"
                                variant="primary"
                                @click="onClickUpload"
                            >
                                {{ buttonFileUploadLabel }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="ct_media_upload_v2_compact_button_context_menu">
                            <ct-context-button v-if="uploadUrlFeatureEnabled" :disabled="disabled">
                                <template #button>
                                    <mt-button
                                        :disabled="disabled"
                                        square
                                        variant="primary"
                                        class="ct-media-upload-v2__button-context-menu"
                                    >
                                        <mt-icon name="regular-chevron-down-xs" size="16" />
                                    </mt-button>
                                </template>

                                <ct-context-menu-item class="ct-media-upload-v2__button-url-upload" @click="useUrlUpload">
                                    {{ $t('global.ct-media-upload-v2.buttonUrlUpload') }}
                                </ct-context-menu-item>
                            </ct-context-button>
                        </ct-block>
                    </ct-button-group>

                    <ct-block name="ct_media_upload_v2_compact_url_form">
                        <ct-media-url-form
                            v-if="isUrlUpload"
                            variant="modal"
                            @modal-close="useFileUpload"
                            @media-url-form-submit="onUrlUpload"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_media_upload_v2_regular">
                <div v-if="variant == 'regular'" class="ct-media-upload-v2__content">
                    <ct-block name="ct_media_upload_v2_regular_header"> </ct-block>

                    <ct-block name="ct_media_upload_v2_regular_drop_zone">
                        <div ref="dropzone" class="ct-media-upload-v2__dropzone" :class="isDragActiveClass">
                            <ct-block name="ct_media_upload_v2_preview">
                                <div class="ct-media-compact-upload-v2__preview-wrapper">
                                    <template v-if="allowMultiSelect && mediaPreview">
                                        <div
                                            v-for="item in mediaPreview"
                                            :key="item.name"
                                            class="ct-media-compact-upload-v2__preview-item"
                                        >
                                            <ct-media-preview-v2
                                                class="ct-media-upload-v2__preview"
                                                :source="item"
                                                :media-is-private="privateFilesystem"
                                            />

                                            <ct-context-button>
                                                <slot name="context-menu-items">
                                                    <ct-context-menu-item variant="headline">
                                                        {{ getFileName(item) }}
                                                    </ct-context-menu-item>
                                                    <ct-context-menu-divider />
                                                    <ct-context-menu-item
                                                        v-tooltip.top="{
                                                            message: disableDeletionForLastItem.helpText,
                                                            disabled:
                                                                !isDeletionDisabled || !disableDeletionForLastItem.helpText,
                                                            showOnDisabledElements: true,
                                                        }"
                                                        class="ct-media-upload-v2__delete-item-button ct-context-menu-item__buttonRemove"
                                                        :disabled="isDeletionDisabled"
                                                        variant="danger"
                                                        @click="$emit('delete-item', item)"
                                                    >
                                                        {{ removeFileButtonLabel }}
                                                    </ct-context-menu-item>
                                                </slot>
                                            </ct-context-button>
                                        </div>
                                    </template>

                                    <template v-else-if="!allowMultiSelect && (preview || source)">
                                        <ct-block name="ct_media_upload_v2_regular_preview_file">
                                            <ct-media-preview-v2
                                                v-if="source || preview"
                                                class="ct-media-upload-v2__preview"
                                                :source="source || preview"
                                                :media-is-private="privateFilesystem"
                                            />
                                        </ct-block>
                                    </template>

                                    <ct-block name="ct_media_upload_v2_regular_preview_fallback">
                                        <template
                                            v-if="
                                                (allowMultiSelect && mediaPreview) ||
                                                (!allowMultiSelect && (preview || source))
                                            "
                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                        >
                                        <div v-else class="ct-media-upload-v2__preview is--fallback">
                                            <mt-icon class="ct-media-upload-v2__fallback-icon" name="regular-image" />
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="ct_media_upload_v2_actions">
                                <div class="ct-media-upload-v2__actions" :class="{ 'has--source': source }">
                                    <div v-if="source" class="ct-media-upload-v2__file-info">
                                        <div class="ct-media-upload-v2__file-headline">
                                            {{ mediaNameFilter(source) }}
                                        </div>
                                        <mt-icon
                                            class="ct-media-upload-v2__remove-icon"
                                            name="regular-times-xs"
                                            @click="onRemoveMediaItem"
                                        />
                                    </div>

                                    <template v-else>
                                        <ct-block name="ct_media_upload_v2_regular_actions_url">
                                            <ct-media-url-form
                                                v-if="isUrlUpload"
                                                class="ct-media-upload-v2__url-form"
                                                variant="inline"
                                                @media-url-form-submit="onUrlUpload"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_media_upload_v2_regular_actions_add">
                                            <template v-if="!isUrlUpload">
                                                <ct-block name="ct_media_upload_v2_regular_upload_button">
                                                    <mt-button
                                                        class="ct-media-upload-v2__button upload"
                                                        :disabled="isLoading || disabled"
                                                        size="small"
                                                        variant="secondary"
                                                        @click="onClickUpload"
                                                    >
                                                        {{ buttonFileUploadLabel }}
                                                    </mt-button>
                                                </ct-block>

                                                <template v-if="!privateFilesystem">
                                                    <mt-button
                                                        variant="primary"
                                                        :disabled="disabled"
                                                        class="ct-media-compact-upload-v2__browse-button"
                                                        @click="mediaModalIsOpen = true"
                                                    >
                                                        <mt-icon
                                                            class="ct-media-compact-upload-v2__browse-icon"
                                                            name="regular-image"
                                                        />
                                                    </mt-button>

                                                    <ct-media-modal-v2
                                                        v-if="mediaModalIsOpen"
                                                        :allow-multi-select="false"
                                                        @modal-close="closeModal()"
                                                        @media-modal-selection-change="onModalClosed"
                                                    />
                                                </template>

                                                <ct-block name="ct_media_upload_v2_regular_media_sidebar_button"></ct-block>
                                            </template>
                                        </ct-block>
                                    </template>
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_media_upload_v2_file_input">
                <form ref="fileForm" class="ct-media-upload-v2__form">
                    <input
                        id="files"
                        ref="fileInput"
                        class="ct-media-upload-v2__file-input"
                        type="file"
                        :accept="fileAccept"
                        :multiple="multiSelect"
                        @change="onFileInputChange"
                    />
                </form>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-compact-upload-v2.scss';

const props = defineProps({
    allowMultiSelect: {
        type: Boolean,
        required: false,
        default: false,
    },

    disableDeletionForLastItem: {
        type: Object,
        validator(value) {
            return Object(value).hasOwnProperty('value') && Object(value).hasOwnProperty('helpText');
        },
        required: false,
        default: () => {
            return {
                value: false,
                helpText: null,
            };
        },
    },

    variant: {
        type: String,
        required: false,
        validValues: [
            'compact',
            'regular',
        ],
        validator(value) {
            return [
                'compact',
                'regular',
            ].includes(value);
        },
        default: 'regular',
    },

    source: {
        type: [
            String,
            Object,
        ],
        required: false,
        default: '',
    },

    sourceMultiselect: {
        type: Array,
        required: false,
        default: () => {
            return [];
        },
    },

    fileAccept: {
        type: String,
        required: false,
        default: 'image/*',
    },

    removeButtonLabel: {
        type: String,
        required: false,
        default: '',
    },
});
const emit = defineEmits([
    'delete-item',
    'selection-change',
]);

import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const { preview } = Contena.Component.getExtensionParentSetup();
const mediaModalIsOpen = ref(false);

const mediaPreview = computed(() => {
    if (!props.allowMultiSelect) {
        return props.source || preview.value;
    }

    return props.sourceMultiselect || null;
});

const removeFileButtonLabel = computed(() => {
    if (props.removeButtonLabel === '') {
        return t('global.default.remove');
    }

    return props.removeButtonLabel;
});
const isDeletionDisabled = computed(() => {
    if (!props.disableDeletionForLastItem.value) {
        return false;
    }

    return props.sourceMultiselect.length <= 1;
});
const mediaNameFilter = computed(() => {
    return Contena.Filter.getByName('mediaName');
});

const closeModal = () => {
    mediaModalIsOpen.value = false;
};
const onModalClosed = (selection) => {
    emit('selection-change', selection, props.uploadTag);
};
const getFileName = (item) => {
    if (item.name) {
        return item.name;
    }

    return `${item.fileName}.${item.fileExtension}`;
};

ctDefinePublic({
    mediaModalIsOpen,
    mediaPreview,
    removeFileButtonLabel,
    isDeletionDisabled,
    mediaNameFilter,
    closeModal,
    onModalClosed,
    getFileName,
});

defineExpose({
    mediaModalIsOpen,
    mediaPreview,
    removeFileButtonLabel,
    isDeletionDisabled,
    mediaNameFilter,
    closeModal,
    onModalClosed,
    getFileName,
});
</script>
