<template>
    <ct-block name="ct_channel_switch">
        <div class="ct-channel-switch">
            <ct-modal
                v-if="showUnsavedChangesModal"
                :title="t('ct-channel-switch.titleModalUnsavedChanges')"
                variant="small"
                @modal-close="onCloseChangesModal"
            >
                <ct-block name="ct_channel_switch_message">
                    <p>{{ t('ct-channel-switch.messageModalUnsavedChanges') }}</p>
                </ct-block>
                <template #modal-footer>
                    <ct-block name="ct_channel_switch_footer">
                        <mt-button size="small" variant="secondary" @click="onCloseChangesModal">
                            {{ t('global.default.cancel') }}
                        </mt-button>
                        <mt-button size="small" variant="secondary" @click="onClickRevertUnsavedChanges">
                            {{ t('ct-channel-switch.titleModalButtonRevertUnsavedChanges') }}
                        </mt-button>
                        <mt-button size="small" variant="primary" @click="onClickSaveChanges">
                            {{ t('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </template>
            </ct-modal>

            <ct-block name="ct_channel_switch_select">
                <mt-entity-select
                    id="channelSelect"
                    v-model="channelId"
                    entity="channel"
                    label-property="name"
                    :criteria="channelCriteria"
                    :disabled="disabled || undefined"
                    :label="label"
                    :placeholder="t('ct-channel-switch.labelDefaultOption')"
                    show-clearable-button
                    @update:model-value="onInput"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { Criteria } = Contena.Data;
const { t } = useI18n();

const props = defineProps({
    disabled: { type: Boolean, default: false },
    abortChangeFunction: { type: Function, default: null },
    saveChangesFunction: { type: Function, default: null },
    label: { type: String, default: '' },
});
const emit = defineEmits(['change-channel-id']);

const channelId = ref(null);
const lastChannelId = ref(null);
const newChannelId = ref(null);
const showUnsavedChangesModal = ref(false);
const channelCriteria = new Criteria(1, 25);
channelCriteria.addSorting(Criteria.sort('name'));

const onInput = (id) => {
    channelId.value = id;
    newChannelId.value = id;

    if (
        typeof props.abortChangeFunction === 'function' &&
        props.abortChangeFunction({
            oldChannelId: lastChannelId.value,
            newChannelId: id,
        })
    ) {
        showUnsavedChangesModal.value = true;
        channelId.value = lastChannelId.value;

        return;
    }

    emitChange();
};
function emitChange() {
    lastChannelId.value = channelId.value;
    emit('change-channel-id', channelId.value);
}
const onCloseChangesModal = () => {
    showUnsavedChangesModal.value = false;
    newChannelId.value = null;
};
const changeToNewChannel = () => {
    channelId.value = newChannelId.value;
    newChannelId.value = null;
    emitChange();
    onCloseChangesModal();
};
const onClickSaveChanges = () => {
    const save = typeof props.saveChangesFunction === 'function' ? props.saveChangesFunction() : null;

    return Promise.resolve(save).then(changeToNewChannel);
};
const onClickRevertUnsavedChanges = changeToNewChannel;

ctDefinePublic({
    channelId,
    newChannelId,
    showUnsavedChangesModal,
    channelCriteria,
    onInput,
    onCloseChangesModal,
    onClickSaveChanges,
    onClickRevertUnsavedChanges,
});

defineExpose({
    channelId,
    newChannelId,
    showUnsavedChangesModal,
    channelCriteria,
    onInput,
    onCloseChangesModal,
    onClickSaveChanges,
    onClickRevertUnsavedChanges,
});
</script>
