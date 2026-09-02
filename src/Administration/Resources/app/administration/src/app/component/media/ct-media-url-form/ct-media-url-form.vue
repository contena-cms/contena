<template>
    <ct-block name="ct_media_url_form">
        <mt-modal-root v-if="variant === 'modal'" :is-open="showModal" @change="onModalChange">
            <mt-modal class="ct-media-url-form" variant="small" :title="$t('global.ct-media-url-form.title')">
                <ct-block name="ct_media_url_form_input">
                    <mt-text-field
                        v-model="url"
                        class="ct-media-url-form__url-input"
                        label="URL"
                        :error="invalidUrlError"
                        :placeholder="$t('global.ct-media-url-form.example')"
                        name="ct-field--url"
                    />

                    <mt-text-field
                        v-if="missingFileExtension"
                        v-model="extensionFromInput"
                        class="ct-media-url-form__extension-input"
                        :label="$t('global.ct-media-url-form.labelFileExtension')"
                        validation="required"
                        placeholder="jpg"
                        :help-text="$t('global.ct-media-url-form.missingFileExtension')"
                    />
                </ct-block>

                <template #footer>
                    <ct-block name="ct_media_url_form_footer">
                        <ct-block name="ct_media_url_form_cancel_button">
                            <mt-button size="small" variant="secondary" @click="onModalChange(false)">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="ct_media_url_form_submit_button">
                            <mt-button
                                class="ct-media-url-form__submit-button"
                                variant="primary"
                                size="small"
                                :disabled="!isValid"
                                @click.prevent="emitUrl"
                            >
                                {{ $t('global.ct-media-url-form.upload') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>

        <div v-else-if="variant === 'inline'">
            <ct-block name="ct_media_url_form_input_inline">
                <mt-text-field
                    v-model="url"
                    class="ct-media-url-form__url-input"
                    label="URL"
                    :error="invalidUrlError"
                    :placeholder="$t('global.ct-media-url-form.example')"
                    name="ct-field--url"
                />

                <mt-text-field
                    v-if="missingFileExtension"
                    v-model="extensionFromInput"
                    class="ct-media-url-form__extension-input"
                    :label="$t('global.ct-media-url-form.labelFileExtension')"
                    validation="required"
                    placeholder="jpg"
                    :help-text="$t('global.ct-media-url-form.missingFileExtension')"
                />
            </ct-block>

            <mt-button
                class="ct-media-url-form__submit-button"
                :disabled="!isValid"
                size="small"
                variant="primary"
                @click="emitUrl"
            >
                {{ $t('global.ct-media-url-form.upload') }}
            </mt-button>
        </div>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    variant: {
        type: String,
        required: true,
        validValues: [
            'modal',
            'inline',
        ],
        validator(value) {
            return [
                'modal',
                'inline',
            ].includes(value);
        },
        default: 'inline',
    },
});
const emit = defineEmits([
    'media-url-form-submit',
    'modal-close',
]);

import { ref, computed, watch, onMounted } from 'vue';

const url = ref('');
const extensionFromUrl = ref('');
const extensionFromInput = ref('');
const showModal = ref(false);

const urlObject = computed(() => {
    try {
        return new URL(url.value);
    } catch (_e) {
        return null;
    }
});
const hasInvalidInput = computed(() => {
    return urlObject.value === null && url.value !== '';
});
const invalidUrlError = computed(() => {
    if (hasInvalidInput.value) {
        return { code: 'INVALID_MEDIA_URL' };
    }

    return null;
});
const missingFileExtension = computed(() => {
    return urlObject.value !== null && !extensionFromUrl.value;
});
const fileExtension = computed(() => {
    return extensionFromUrl.value || extensionFromInput.value;
});
const isValid = computed(() => {
    return urlObject.value !== null && fileExtension.value;
});

const mountedComponent = () => {
    if (props.variant === 'modal') {
        showModal.value = true;
    }
};
const emitUrl = (originalDomEvent) => {
    if (isValid.value) {
        emit('media-url-form-submit', {
            originalDomEvent,
            url: urlObject.value,
            fileExtension: fileExtension.value,
        });

        if (props.variant === 'modal') {
            showModal.value = false;
        }
    }
};
const onModalChange = (isOpen) => {
    showModal.value = isOpen;
    if (!isOpen) {
        emit('modal-close');
    }
};

watch(
    () => urlObject.value,
    () => {
        if (urlObject.value === null) {
            extensionFromUrl.value = '';
            return;
        }

        const fileName = urlObject.value.pathname.split('/').pop();
        if (fileName.split('.').length === 1) {
            extensionFromUrl.value = '';
            return;
        }

        extensionFromUrl.value = fileName.split('.').pop();
    },
);

onMounted(() => {
    mountedComponent();
});

ctDefinePublic({
    url,
    extensionFromUrl,
    extensionFromInput,
    showModal,
    urlObject,
    hasInvalidInput,
    invalidUrlError,
    missingFileExtension,
    fileExtension,
    isValid,
    mountedComponent,
    emitUrl,
    onModalChange,
});

defineExpose({
    url,
    extensionFromUrl,
    extensionFromInput,
    showModal,
    urlObject,
    hasInvalidInput,
    invalidUrlError,
    missingFileExtension,
    fileExtension,
    isValid,
    mountedComponent,
    emitUrl,
    onModalChange,
});
</script>
