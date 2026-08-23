<template>
    <ct-block name="sw_field_error">
        <div v-if="!!error" class="ct-field__error">
            <mt-icon name="solid-exclamation-circle" size="12" />
            {{ errorMessage }}
        </div>
    </ct-block>
</template>

<script setup>
import './ct-field-error.scss';

const props = defineProps({
    error: {
        type: Object,
        required: false,
        default: null,
    },
});

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const errorMessage = computed(() => {
    if (!props.error) {
        return '';
    }

    const translationKey = `global.error-codes.${props.error.code}`;
    const translation = t(translationKey, 1, formatParameters(props.error.parameters) || {});

    if (translation === translationKey) {
        return props.error.detail;
    }
    return translation;
});

function formatParameters(parameters) {
    if (!parameters || Object.keys(parameters).length < 1) {
        return {};
    }
    const formattedParameters = {};
    Object.keys(parameters).forEach((key) => {
        if (parameters.hasOwnProperty(key)) {
            const formattedKey = key.replace(/{{\s*(.*?)\s*}}/, '$1');
            formattedParameters[formattedKey] = parameters[key];
        }
    });
    return formattedParameters;
}

swDefinePublic({
    errorMessage,
    formatParameters,
});

defineExpose({
    errorMessage,
    formatParameters,
});
</script>
