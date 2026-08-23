<template>
    <ct-block name="sw_extension_removal_modal">
        <ct-modal class="ct-extension-removal-modal" :title="title" variant="small" @modal-close="emitClose">
            <template #default>
                <ct-block name="sw_installed_extension_card_removal_content_modal_body">
                    <p>
                        {{ $t('ct-extension.component.ct-extension-removal-modal.description') }}
                    </p>

                    <p class="ct-extension-removal-modal__bold-paragraph">
                        {{ alert }}
                    </p>
                </ct-block>
            </template>

            <template #modal-footer>
                <ct-block name="sw_extension_removal_modal_footer">
                    <ct-block name="sw_extension_removal_modal_footer_cancel">
                        <mt-button size="small" :disabled="isLoading" variant="secondary" @click="emitClose">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_extension_removal_modal_footer_deactivate">
                        <mt-button variant="critical" size="small" :is-loading="isLoading" @click="emitRemoval">
                            {{ btnLabel }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-extension-removal-modal.scss';

const props = defineProps({
    extensionName: {
        type: String,
        required: true,
    },
    isLoading: {
        type: Boolean,
        required: true,
    },
});
const emit = defineEmits([
    'modal-close',
    'remove-extension',
]);

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const title = computed(() => {
    return t('ct-extension.component.ct-extension-removal-modal.titleRemove', {
        extensionName: props.extensionName,
    });
});
const alert = computed(() => {
    return t('ct-extension.component.ct-extension-removal-modal.alertRemove');
});
const btnLabel = computed(() => {
    return t('global.default.remove');
});

const emitClose = () => {
    if (props.isLoading) {
        return;
    }

    emit('modal-close');
};
const emitRemoval = () => {
    emit('remove-extension');
};

swDefinePublic({
    title,
    alert,
    btnLabel,
    emitClose,
    emitRemoval,
});

defineExpose({
    title,
    alert,
    btnLabel,
    emitClose,
    emitRemoval,
});
</script>
