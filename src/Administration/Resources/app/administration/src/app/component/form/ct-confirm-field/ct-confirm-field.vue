<template>
    <ct-block name="sw_confirm_field">
        <div class="ct-confirm-field" :class="confirmFieldClasses">
            <ct-block name="sw_confirm_field_input_field">
                <mt-text-field
                    v-model="draft"
                    v-bind="$attrs"
                    :required="required"
                    :disabled="disabled"
                    :error="error"
                    validation="required"
                    @focus="onStartEditing"
                    @blur="onBlurField"
                    @keyup.enter="onSubmitFromKey"
                    @keyup.esc="onCancelFromKey"
                    @update:model-value="onInput"
                />
            </ct-block>

            <ct-block name="sw_confirm_field_button_list">
                <span v-show="isEditing" class="ct-confirm-field__button-list">
                    <ct-block name="sw_confirm_field_cancel_button">
                        <mt-button
                            :disabled="disabled"
                            class="ct-confirm-field__button ct-confirm-field__button--cancel"
                            square
                            size="x-small"
                            tabindex="-1"
                            variant="secondary"
                            @click="onCancelSubmit"
                        >
                            <ct-block name="sw_field_inline_cancel_submit_button_icon">
                                <mt-icon size="10px" name="regular-times-xs" />
                            </ct-block>
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_confirm_field_confirm_button">
                        <mt-button
                            class="ct-confirm-field__button ct-confirm-field__button--submit"
                            :disabled="(preventEmptySubmit && !draft) || disabled"
                            square
                            size="x-small"
                            variant="primary"
                            tabindex="-1"
                            @click="onSubmitValue"
                        >
                            <ct-block name="sw_field_inline_submit_button_icon">
                                <mt-icon size="10px" name="regular-checkmark-xxs" />
                            </ct-block>
                        </mt-button>
                    </ct-block>
                </span>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-confirm-field.scss';

const props = defineProps({
    value: {
        type: String,
        required: false,
        default: '',
    },

    compact: {
        type: Boolean,
        required: false,
        default: false,
    },

    preventEmptySubmit: {
        type: Boolean,
        required: false,
        default: false,
    },

    required: {
        type: Boolean,
        required: false,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    error: {
        type: Object,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'remove-error',
    'blur',
    'submit-cancel',
    'input',
]);

import { ref, computed, watch, onBeforeUnmount } from 'vue';

const hasSubmittedFromKey = ref(false);
const isEditing = ref(false);
const draft = ref(props.value);
const event = ref(null);

const confirmFieldClasses = computed(() => {
    return {
        'ct-confirm-field--compact': props.compact,
        'ct-confirm-field--editing': isEditing.value,
        'has--error': !!props.error,
    };
});

const removeActionButtons = () => {
    isEditing.value = false;
};
const onStartEditing = () => {
    isEditing.value = true;
};
const onBlurField = (event) => {
    if (event?.relatedTarget?.classList.contains('ct-confirm-field__button') || hasSubmittedFromKey.value) {
        hasSubmittedFromKey.value = false;
        return;
    }
    emit('blur');
    cancelSubmit();
};
function cancelSubmit() {
    removeActionButtons();
    draft.value = props.value;
}
const onCancelFromKey = ({ target }) => {
    cancelSubmit();
    target.blur();
};
const onCancelSubmit = () => {
    emit('submit-cancel');
    cancelSubmit();
    isEditing.value = false;
};
const submitValue = () => {
    if (draft.value !== props.value) {
        emit('input', draft.value, event.value);
    }
};
const onSubmitFromKey = () => {
    hasSubmittedFromKey.value = true;
    event.value = 'key';
    submitValue();
    isEditing.value = false;
};
const onSubmitValue = () => {
    event.value = 'click';
    submitValue();
    isEditing.value = false;
};
const onInput = () => {
    emit('remove-error');
};

watch(
    () => props.value,
    () => {
        draft.value = props.value;
    },
);

onBeforeUnmount(() => {
    emit('remove-error');
});

swDefinePublic({
    hasSubmittedFromKey,
    isEditing,
    draft,
    event,
    confirmFieldClasses,
    removeActionButtons,
    onStartEditing,
    onBlurField,
    cancelSubmit,
    onCancelFromKey,
    onCancelSubmit,
    submitValue,
    onSubmitFromKey,
    onSubmitValue,
    onInput,
});

defineExpose({
    hasSubmittedFromKey,
    isEditing,
    draft,
    event,
    confirmFieldClasses,
    removeActionButtons,
    onStartEditing,
    onBlurField,
    cancelSubmit,
    onCancelFromKey,
    onCancelSubmit,
    submitValue,
    onSubmitFromKey,
    onSubmitValue,
    onInput,
});
</script>
