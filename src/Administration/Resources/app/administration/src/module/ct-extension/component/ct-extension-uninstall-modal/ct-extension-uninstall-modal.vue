<template>
    <ct-block name="ct_extension_uninstall_modal">
        <ct-modal class="ct-extension-uninstall-modal" :title="title" variant="small" @modal-close="emitClose">
            <template #default>
                <ct-block name="ct_installed_extension_card_removal_content_modal_body">
                    <p>
                        {{ $t('ct-extension.component.ct-extension-uninstall-modal.description') }}
                    </p>

                    <p class="ct-extension-uninstall-modal__bold-paragraph">
                        {{ $t('ct-extension.component.ct-extension-uninstall-modal.alert') }}
                    </p>

                    <p>
                        <mt-switch
                            v-model="removePluginData"
                            :label="$t('ct-extension.component.ct-extension-uninstall-modal.labelRemovePluginData')"
                            :help-text="$t('ct-extension.component.ct-extension-uninstall-modal.helpTextRemovePluginData')"
                        />
                    </p>
                </ct-block>
            </template>

            <template #modal-footer>
                <ct-block name="ct_extension_uninstall_modal_footer">
                    <ct-block name="ct_extension_uninstall_modal_footer_cancel">
                        <mt-button size="small" :disabled="isLoading" variant="secondary" @click="emitClose">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_extension_uninstall_modal_footer_uninstall">
                        <mt-button variant="critical" size="small" :is-loading="isLoading" @click="emitUninstall">
                            {{ $t('ct-extension.component.ct-extension-uninstall-modal.buttonLabel') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-extension-uninstall-modal.scss';

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
    'uninstall-extension',
]);

import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const removePluginData = ref(false);

const title = computed(() => {
    return t('ct-extension.component.ct-extension-uninstall-modal.title', {
        extensionName: props.extensionName,
    });
});

const emitClose = () => {
    if (props.isLoading) {
        return;
    }

    emit('modal-close');
};
const emitUninstall = () => {
    emit('uninstall-extension', removePluginData.value);
};

ctDefinePublic({
    removePluginData,
    title,
    emitClose,
    emitUninstall,
});

defineExpose({
    removePluginData,
    title,
    emitClose,
    emitUninstall,
});
</script>
