<template>
    <ct-block name="ct_field_copyable">
        <!-- eslint-disable-next-line vuejs-accessibility/mouse-events-have-key-events -->
        <mt-icon
            v-tooltip="{
                message: tooltipText,
                width: 220,
                position: 'top',
                showDelay: 300,
                hideDelay: 0,
            }"
            class="ct-field-copyable"
            name="regular-copy-s"
            @click="copyToClipboard"
            @mouseleave="resetTooltipText"
        />
    </ct-block>
</template>

<script setup>
import './ct-field-copyable.scss';
const domUtils = Contena.Utils.dom;

const props = defineProps({
    copyableText: {
        type: String,
        required: false,
        default: null,
    },

    tooltip: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationInfo, createNotificationError } = useNotification();

const wasCopied = ref(false);

const tooltipText = computed(() => {
    if (wasCopied.value) {
        return t('global.ct-field-copyable.tooltip.wasCopied');
    }

    return t('global.ct-field-copyable.tooltip.canCopy');
});

const copyToClipboard = async () => {
    if (!props.copyableText) {
        return;
    }

    try {
        await domUtils.copyStringToClipboard(props.copyableText);
        if (props.tooltip) {
            tooltipSuccess();
        } else {
            notificationSuccess();
        }
    } catch (_err) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.ct-field.notification.notificationCopyFailureMessage'),
        });
    }
};
function tooltipSuccess() {
    wasCopied.value = true;
}
function notificationSuccess() {
    createNotificationInfo({
        message: t('global.ct-field.notification.notificationCopySuccessMessage'),
    });
}
const resetTooltipText = () => {
    wasCopied.value = false;
};

ctDefinePublic({
    wasCopied,
    tooltipText,
    copyToClipboard,
    tooltipSuccess,
    notificationSuccess,
    resetTooltipText,
});

defineExpose({
    wasCopied,
    tooltipText,
    copyToClipboard,
    tooltipSuccess,
    notificationSuccess,
    resetTooltipText,
});
</script>
