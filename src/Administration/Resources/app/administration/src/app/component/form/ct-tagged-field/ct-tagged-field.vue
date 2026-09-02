<template>
    <ct-block name="ct_tagged_field">
        <ct-block-field class="ct-tagged-field" :class="taggedFieldClasses" v-bind="$attrs">
            <template #ct-field-input="{ disabled: isFieldDisabled, size }">
                <ct-block name="ct_tagged_field_inner">
                    <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                    <ul class="ct-tagged-field__tag-list" @click="setFocus(true)">
                        <li v-for="(tag, index) in value" :key="`ct-tagged-field-value--${index}`">
                            <ct-label
                                :dismissable="!disabled && !isFieldDisabled"
                                :size="size"
                                @selected="setFocus(true)"
                                @dismiss="dismissTag(index)"
                            >
                                {{ tag }}
                            </ct-label>
                        </li>
                        <ct-block name="ct_tagged_field_item_input">
                            <li class="ct-tagged-field__input-list-entry">
                                <ct-block name="ct_tagged_field_input">
                                    <!-- eslint-disable-next-line vuejs-accessibility/form-control-has-label -->
                                    <input
                                        ref="taggedFieldInput"
                                        v-model="newTagName"
                                        type="text"
                                        class="ct-tagged-field__input"
                                        :class="taggedFieldInputClasses"
                                        :disabled="disabled || isFieldDisabled"
                                        :placeholder="placeholder"
                                        @focus="setFocus(true)"
                                        @blur="setFocus(false)"
                                        @keydown="performAddTag"
                                        @keydown.delete="dismissLastTag"
                                    />
                                </ct-block>
                            </li>
                        </ct-block>
                    </ul>
                </ct-block>
            </template>

            <template #label>
                <slot name="label"></slot>
            </template>

            <template #hint>
                <slot name="hint"></slot>
            </template>
        </ct-block-field>
    </ct-block>
</template>

<script setup>
import './ct-tagged-field.scss';

const props = defineProps({
    value: {
        type: Array,
        required: false,
        default: () => [],
    },

    placeholder: {
        type: String,
        required: false,
        default() {
            return Contena.Snippet.tc('global.ct-tagged-field.text-default-placeholder');
        },
    },

    addOnKey: {
        type: Array,
        required: false,
        default: () => ['enter'],
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits(['update:value']);

import { ref, computed, inject } from 'vue';

const taggedFieldInput = ref(null);

const feature = inject('feature');

const newTagName = ref('');
const hasFocus = ref(false);

const hasValues = computed(() => {
    return props.value.length > 0;
});
const taggedFieldClasses = computed(() => {
    return {
        'has--focus': hasFocus.value,
    };
});
const taggedFieldInputClasses = computed(() => {
    return {
        'ct-tagged-field__input--full-width': !hasValues.value,
        'ct-tagged-field__input--hidden': hasValues.value && !hasFocus.value,
    };
});

const dismissLastTag = () => {
    if (typeof newTagName.value === 'string' && newTagName.value.length > 0) {
        return;
    }

    emit('update:value', props.value.slice(0, props.value.length - 1));
};
const dismissTag = (index) => {
    emit(
        'update:value',
        props.value.filter((item, itemIndex) => itemIndex !== index),
    );
};
const performAddTag = (event) => {
    if (props.disabled || noTriggerKey(event)) {
        return;
    }

    if (typeof newTagName.value !== 'string' || newTagName.value === '') {
        return;
    }

    emit('update:value', [
        ...props.value,
        newTagName.value,
    ]);
    newTagName.value = '';
};
const setFocus = (isFocused) => {
    hasFocus.value = isFocused;
    if (isFocused) {
        taggedFieldInput.value.focus();
    }
};
function noTriggerKey(event) {
    const keyIndex = props.addOnKey.findIndex((eventKey) => {
        return eventKey.toLowerCase() === event.key.toLowerCase();
    });
    if (keyIndex === -1) {
        return true;
    }
    event.preventDefault();
    return false;
}

ctDefinePublic({
    feature,
    newTagName,
    hasFocus,
    hasValues,
    taggedFieldClasses,
    taggedFieldInputClasses,
    dismissLastTag,
    dismissTag,
    performAddTag,
    setFocus,
    noTriggerKey,
});

defineExpose({
    feature,
    newTagName,
    hasFocus,
    hasValues,
    taggedFieldClasses,
    taggedFieldInputClasses,
    dismissLastTag,
    dismissTag,
    performAddTag,
    setFocus,
    noTriggerKey,
});
</script>
