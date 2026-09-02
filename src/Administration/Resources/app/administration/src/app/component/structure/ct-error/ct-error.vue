<template>
    <ct-block name="ct_error">
        <div class="ct-error">
            <div class="ct-error__container">
                <ct-block name="ct_error_illustration">
                    <div class="ct-error__illustration">
                        <img :src="imagePath" alt="" />
                    </div>
                </ct-block>

                <div class="ct-error__content">
                    <slot>
                        <ct-block name="ct_error_contend">
                            <ct-block name="ct_error_status_code">
                                <div class="ct-error__status-code">
                                    {{ statusCode }}
                                </div>
                            </ct-block>

                            <ct-block name="ct_error_message">
                                <div class="ct-error__message">
                                    {{ message }}
                                </div>
                            </ct-block>

                            <ct-block name="ct_error_stack">
                                <code v-if="showStack" class="ct-error__stack">{{ error.stack }}</code>
                            </ct-block>

                            <ct-block name="ct_error_link">
                                <mt-button v-if="showLink" variant="primary" @click="$router.push(routerLink)">
                                    {{ displayLinkText }}
                                </mt-button>
                            </ct-block>
                        </ct-block>
                    </slot>
                </div>
            </div>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-error.scss';

const props = defineProps({
    errorObject: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },
    routerLink: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },
    linkText: {
        type: String,
        required: false,
        default: '',
    },
});

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const error = computed(() => {
    if (Object.keys(props.errorObject).length > 0) {
        return props.errorObject;
    }
    return Contena.Application.view?.root?.initError ?? {};
});
const imagePath = computed(() => {
    return '/administration/static/img/error.svg';
});
const message = computed(() => {
    if (!error.value.message) {
        return t('ct-error.general.messagePlaceholder');
    }
    return error.value.message;
});
const statusCode = computed(() => {
    if (!error.value.response) {
        return t('global.default.error');
    }

    return error.value.response.status;
});
const showStack = computed(() => {
    return process.env.NODE_ENV === 'development' && error.value.stack;
});
const showLink = computed(() => {
    return Object.keys(props.routerLink).length > 0;
});
const displayLinkText = computed(() => props.linkText || t('global.default.back'));

ctDefinePublic({
    error,
    imagePath,
    message,
    statusCode,
    showStack,
    showLink,
    displayLinkText,
});

defineExpose({
    error,
    imagePath,
    message,
    statusCode,
    showStack,
    showLink,
    displayLinkText,
});
</script>
