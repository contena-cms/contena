<template>
    <ct-upload-listener :upload-tag="uploadTag" auto-upload @media-upload-finish="successfulUpload" />

    <ct-media-compact-upload-v2
        :upload-tag="uploadTag"
        :source="value"
        :label="label"
        :name="name"
        :disabled="disabled"
        @media-upload-remove-image="removeMedia"
        @selection-change="setMedia"
    />
</template>

<script setup>
defineProps({
    value: {
        type: String,
        required: false,
        default: null,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    label: {
        type: String,
        required: false,
        default: null,
    },

    name: {
        type: String,
        required: false,
        default: null,
    },
});
const emit = defineEmits(['update:value']);

import { ref } from 'vue';

const uploadTag = ref(`ct-system-config-media-upload-${Contena.Utils.createId()}`);

const successfulUpload = ({ targetId }) => {
    emit('update:value', targetId);
};
const setMedia = (selection) => {
    emit('update:value', selection.at(0)?.id ?? null);
};
const removeMedia = () => {
    emit('update:value', null);
};

swDefinePublic({
    uploadTag,
    successfulUpload,
    setMedia,
    removeMedia,
});

defineExpose({
    uploadTag,
    successfulUpload,
    setMedia,
    removeMedia,
});
</script>
