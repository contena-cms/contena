<template>
    <ct-block name="sw_language_switch">
        <div class="ct-language-switch">
            <ct-block name="sw_language_switch_modal">
                <ct-modal
                    v-if="showUnsavedChangesModal"
                    :title="$t('ct-language-switch.titleModalUnsavedChanges')"
                    variant="small"
                    @modal-close="onCloseChangesModal"
                >
                    <ct-block name="sw_language_switch_message">
                        <p>{{ $t('ct-language-switch.messageModalUnsavedChanges') }}</p>
                    </ct-block>

                    <template #modal-footer>
                        <ct-block name="sw_language_switch_footer">
                            <ct-block name="sw_language_switch_footer_button_close">
                                <mt-button
                                    id="ct-language-switch-close-button"
                                    size="small"
                                    variant="secondary"
                                    @click="onCloseChangesModal"
                                >
                                    {{ $t('global.default.cancel') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="sw_language_switch_footer_button_revert">
                                <mt-button
                                    id="ct-language-switch-revert-changes-button"
                                    size="small"
                                    variant="secondary"
                                    @click="onClickRevertUnsavedChanges"
                                >
                                    {{ $t('ct-language-switch.titleModalButtonRevertUnsavedChanges') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="sw_language_switch_footer_button_save">
                                <mt-button
                                    id="ct-language-switch-save-changes-button"
                                    v-tooltip="{
                                        message: $t('ct-privileges.tooltip.warning'),
                                        disabled: allowEdit,
                                        showOnDisabledElements: true,
                                    }"
                                    variant="primary"
                                    :disabled="!allowEdit || undefined"
                                    size="small"
                                    @click="onClickSaveChanges"
                                >
                                    {{ $t('global.default.save') }}
                                </mt-button>
                            </ct-block>
                        </ct-block>
                    </template>
                </ct-modal>
            </ct-block>
            <ct-block name="sw_language_switch_select">
                <ct-entity-single-select
                    id="language"
                    class="ct-language-switch__select"
                    entity="language"
                    :disabled="disabled || undefined"
                    :criteria="languageCriteria"
                    size="small"
                    required
                    :value="languageId"
                    :result-limit="Infinity"
                    @update:value="onInput"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-language-switch.scss';
const { warn } = Contena.Utils.debug;
const { Criteria } = Contena.Data;

const props = defineProps({
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    changeGlobalLanguage: {
        type: Boolean,
        required: false,
        default: true,
    },
    abortChangeFunction: {
        type: Function,
        required: false,
        default: () => {},
    },
    saveChangesFunction: {
        type: Function,
        required: false,
        default: () => {},
    },
    savePermission: {
        type: Boolean,
        required: false,
        default: true,
    },
    allowEdit: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits(['on-change']);

import { ref, onUnmounted } from 'vue';

const languageId = ref('');
const lastLanguageId = ref('');
const newLanguageId = ref('');
const showUnsavedChangesModal = ref(false);

const languageCriteria = ref(new Criteria(1, 25));
languageCriteria.value.addSorting(Criteria.sort('name', 'ASC', false));
languageCriteria.value.addFilter(Criteria.equals('active', true));

const createdComponent = () => {
    languageId.value = Contena.Context.api.languageId;
    lastLanguageId.value = languageId.value;

    Contena.Utils.EventBus.on('on-change-language-clicked', changeToNewLanguage);
};
const destroyedComponent = () => {
    Contena.Utils.EventBus.off('on-change-language-clicked', changeToNewLanguage);
};
const onInput = (selectedLanguageId) => {
    languageId.value = selectedLanguageId;
    newLanguageId.value = selectedLanguageId;

    checkAbort();
};
function checkAbort() {
    // Check if abort function exists und reset the select field if the change should be aborted
    if (typeof props.abortChangeFunction === 'function' && props.savePermission) {
        if (
            props.abortChangeFunction({
                oldLanguageId: lastLanguageId.value,
                newLanguageId: languageId.value,
            })
        ) {
            showUnsavedChangesModal.value = true;
            languageId.value = lastLanguageId.value;
            return;
        }
    }
    emitChange();
}
function emitChange() {
    lastLanguageId.value = languageId.value;
    if (props.changeGlobalLanguage) {
        const contextStore = Contena.Store.get('context');

        // Keep the API context and the persisted administration language in sync.
        // The context store owns this update so repository requests and the next
        // administration boot use the selected language as well.
        contextStore.setApiLanguageId(languageId.value);
        Contena.Utils.EventBus.emit('ct-language-switch-change-application-language', {
            languageId: languageId.value,
        });
    }
    emit('on-change', languageId.value);
}
const onCloseChangesModal = () => {
    showUnsavedChangesModal.value = false;
    newLanguageId.value = '';
};
const onClickSaveChanges = () => {
    let save = {};
    // Check if save function exists and wait for it before changing the language
    if (typeof props.saveChangesFunction === 'function') {
        save = props.saveChangesFunction();
    } else {
        warn('ct-language-switch', 'You need to implement an own save function to save the changes!');
    }
    return Promise.resolve(save).then(() => {
        changeToNewLanguage();
        onCloseChangesModal();
    });
};
const onClickRevertUnsavedChanges = () => {
    changeToNewLanguage();
    onCloseChangesModal();
};
function changeToNewLanguage(requestedLanguageId) {
    if (requestedLanguageId) {
        newLanguageId.value = requestedLanguageId;
    }
    languageId.value = newLanguageId.value;
    newLanguageId.value = '';
    emitChange();
}

createdComponent();

onUnmounted(() => {
    destroyedComponent();
});

swDefinePublic({
    languageId,
    lastLanguageId,
    newLanguageId,
    showUnsavedChangesModal,
    languageCriteria,
    createdComponent,
    destroyedComponent,
    onInput,
    checkAbort,
    emitChange,
    onCloseChangesModal,
    onClickSaveChanges,
    onClickRevertUnsavedChanges,
    changeToNewLanguage,
});

defineExpose({
    languageId,
    lastLanguageId,
    newLanguageId,
    showUnsavedChangesModal,
    languageCriteria,
    createdComponent,
    destroyedComponent,
    onInput,
    checkAbort,
    emitChange,
    onCloseChangesModal,
    onClickSaveChanges,
    onClickRevertUnsavedChanges,
    changeToNewLanguage,
});
</script>
