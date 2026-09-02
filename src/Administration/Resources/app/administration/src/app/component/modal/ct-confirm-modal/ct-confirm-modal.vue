<template>
    <ct-block name="ct_confirm_modal">
        <ct-modal
            class="ct-confirm-modal"
            v-bind="$attrs"
            :variant="variant"
            :title="titleText"
            @modal-close="$emit('close')"
        >
            <ct-block name="ct_confirm_modal_text">
                <slot>
                    <ct-block name="ct_confirm_modal_text_default">
                        <p class="ct-confirm-modal__text">
                            {{ descriptionText }}
                        </p>
                    </ct-block>
                </slot>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_confirm_modal_footer">
                    <ct-block name="ct_confirm_modal_footer_cancel">
                        <mt-button
                            class="ct-confirm-modal__button-cancel"
                            size="small"
                            variant="secondary"
                            @click="$emit('cancel')"
                        >
                            {{ cancelText }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_confirm_modal_footer_confirm">
                        <mt-button
                            class="ct-confirm-modal__button-confirm"
                            :variant="confirmButtonVariant"
                            size="small"
                            @click="$emit('confirm')"
                        >
                            {{ confirmText }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    title: {
        type: String,
        required: false,
        default: '',
    },

    text: {
        type: String,
        required: false,
        default: '',
    },

    textConfirm: {
        type: String,
        required: false,
        default: '',
    },

    variant: {
        type: String,
        required: false,
        default: 'small',
        validValues: [
            'default',
            'small',
            'large',
            'full',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'default',
                'small',
                'large',
                'full',
            ].includes(value);
        },
    },

    type: {
        type: String,
        required: false,
        default: 'confirm',
        validValues: [
            'confirm',
            'delete',
            'yesno',
            'discard',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'confirm',
                'delete',
                'yesno',
                'discard',
            ].includes(value);
        },
    },
});
defineEmits([
    'close',
    'cancel',
    'confirm',
]);

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const titleText = computed(() => {
    if (props.title !== null && props.title.length > 0) {
        return props.title;
    }

    return t('global.default.warning');
});
const descriptionText = computed(() => {
    if (props.text !== null && props.text.length > 0) {
        return props.text;
    }

    return t('ct-confirm-modal.defaultText');
});
const confirmText = computed(() => {
    if (props.textConfirm) {
        return props.textConfirm;
    }

    switch (props.type) {
        case 'delete':
            return t('global.default.delete');
        case 'yesno':
            return t('global.default.yes');
        case 'discard':
            return t('global.default.discard');
        default:
            return t('global.default.confirm');
    }
});
const cancelText = computed(() => {
    if (props.type === 'yesno') {
        return t('global.default.no');
    }

    return t('global.default.cancel');
});
const confirmButtonVariant = computed(() => {
    switch (props.type) {
        case 'delete':
        case 'discard':
            return 'critical';
        default:
            return 'primary';
    }
});

ctDefinePublic({
    titleText,
    descriptionText,
    confirmText,
    cancelText,
    confirmButtonVariant,
});

defineExpose({
    titleText,
    descriptionText,
    confirmText,
    cancelText,
    confirmButtonVariant,
});
</script>
