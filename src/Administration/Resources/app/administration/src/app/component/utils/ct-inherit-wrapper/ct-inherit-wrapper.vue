<template>
    <ct-block name="sw_inherit_wrapper">
        <div
            class="ct-inherit-wrapper"
            :class="{ 'is--inherited': isInherited, 'is--required': required, 'has--parent': hasParent }"
        >
            <ct-block name="sw_inherit_wrapper_toggle">
                <template v-if="label">
                    <ct-block name="sw_inherit_wrapper_toggle_wrapper">
                        <div class="ct-inherit-wrapper__toggle-wrapper">
                            <ct-block name="sw_inherit_wrapper_toggle_wrapper_field">
                                <ct-inheritance-switch
                                    v-if="isInheritField"
                                    :disabled="disabled"
                                    class="ct-inherit-wrapper__inheritance-icon"
                                    :is-inherited="isInherited"
                                    @inheritance-restore="restoreInheritance"
                                    @inheritance-remove="removeInheritance"
                                />
                            </ct-block>

                            <ct-block name="sw_inherit_wrapper_toggle_wrapper_label">
                                <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                                <label class="ct-inherit-wrapper__inheritance-label" :class="labelClasses">
                                    {{ label }}
                                </label>
                            </ct-block>

                            <ct-block name="sw_inherit_wrapper_toggle_wrapper_help_text">
                                <ct-help-text v-if="helpText" class="ct-inherit-wrapper__help-text" :text="helpText" />
                            </ct-block>
                        </div>
                    </ct-block>
                </template>
            </ct-block>

            <ct-block name="sw_inherit_wrapper_content">
                <slot
                    name="content"
                    v-bind="{
                        currentValue,
                        updateCurrentValue,
                        isInherited,
                        isInheritField,
                        toggleInheritance,
                        restoreInheritance,
                        removeInheritance,
                        error,
                        label,
                    }"
                ></slot>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-inherit-wrapper.scss';

const props = defineProps({
    value: {
        required: true,
    },

    inheritedValue: {
        required: true,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    label: {
        type: String,
        required: false,
        default: null,
    },

    required: {
        type: Boolean,
        required: false,
        default: false,
    },

    isAssociation: {
        type: Boolean,
        required: false,
        default: false,
    },

    hasParent: {
        type: Boolean,
        required: false,
        default: undefined,
    },

    // custom inheritation check which returns true or false
    customInheritationCheckFunction: {
        type: Function,
        required: false,
        default: null,
    },

    // custom reset inheritance function
    customRestoreInheritanceFunction: {
        type: Function,
        required: false,
        default: null,
    },

    // custom remove inheritance function
    customRemoveInheritanceFunction: {
        type: Function,
        required: false,
        default: null,
    },

    helpText: {
        type: String,
        required: false,
        default: null,
    },

    error: {
        type: Object,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'update:value',
    'inheritance-restore',
    'inheritance-remove',
]);

import { ref, computed, inject } from 'vue';

const feature = inject('feature');

const forceInheritanceRemove = ref(false);

const currentValue = computed({
    get: () => {
        return isInherited.value ? props.inheritedValue : props.value;
    },
    set: (newValue) => {
        if (isInherited.value && newValue === props.inheritedValue) {
            return;
        }

        if (!isInherited.value && newValue !== props.inheritedValue) {
            if (newValue === null || newValue === undefined || (Array.isArray(newValue) && newValue.length <= 0)) {
                forceInheritanceRemove.value = true;
            } else {
                forceInheritanceRemove.value = false;
            }
            updateValue(newValue, 'restore');
            return;
        }

        removeInheritance(newValue);
    },
});
const isInheritField = computed(() => {
    // manual check if parent exists
    if (props.hasParent !== undefined) {
        return props.hasParent;
    }

    // automatic check if parent for inheritation exists
    return !(props.inheritedValue === null || typeof props.inheritedValue === 'undefined');
});
const isInherited = computed(() => {
    // if parent does not exist or has data or inheritance removing was forced
    if (!isInheritField.value || forceInheritanceRemove.value) {
        return false;
    }

    // if customInheritationCheckFunction exists
    if (typeof props.customInheritationCheckFunction === 'function') {
        return props.customInheritationCheckFunction(props.value);
    }

    // if association or array
    if ((props.isAssociation || Array.isArray(props.value)) && props.value) {
        return props.value.length <= 0;
    }

    return props.value === null || props.value === undefined;
});
const labelClasses = computed(() => {
    return {
        'has--error': !!props.error,
    };
});

const updateCurrentValue = (value) => {
    currentValue.value = value;
};
function updateValue(value, inheritanceEventName) {
    emit('update:value', value);
    emit(`inheritance-${inheritanceEventName}`);
}
const toggleInheritance = () => {
    if (isInherited.value) {
        removeInheritance();
    } else {
        restoreInheritance();
    }
};
function restoreInheritance() {
    forceInheritanceRemove.value = false;

    // if customRestoreInheritanceFunction exists
    if (typeof props.customRestoreInheritanceFunction === 'function') {
        updateValue(props.customRestoreInheritanceFunction(props.value), 'restore');
        return;
    }

    // if association
    if (props.isAssociation) {
        // remove all items from value
        props.value.getIds().forEach((id) => {
            props.value.remove(id);
        });

        // return new value
        updateValue(props.value, 'restore');
        return;
    }
    emit('update:value', null);
}
function removeInheritance(newValue = currentValue.value) {
    // if customRemoveInheritanceFunction exists
    if (typeof props.customRemoveInheritanceFunction === 'function') {
        updateValue(props.customRemoveInheritanceFunction(newValue, props.value), 'remove');
        return;
    }

    // if association
    if (props.isAssociation && newValue && props.value) {
        // remove all items
        restoreInheritance();
        if (newValue.length <= 0) {
            forceInheritanceRemove.value = true;
        }

        // add each item from the parentValue to the original value
        newValue.forEach((item) => {
            props.value.add(item);
        });
        updateValue(props.value, 'remove');
        return;
    }

    // The user explicitly detached this field from the inherited value.
    // Persist that intent so the field does not silently re-inherit once it
    // becomes empty later (e.g. when the last value of a multi-select is
    // removed). Re-linking via restoreInheritance() resets the flag again.
    forceInheritanceRemove.value = true;
    emit('update:value', newValue);
}

swDefinePublic({
    feature,
    forceInheritanceRemove,
    currentValue,
    isInheritField,
    isInherited,
    labelClasses,
    updateCurrentValue,
    updateValue,
    toggleInheritance,
    restoreInheritance,
    removeInheritance,
});

defineExpose({
    feature,
    forceInheritanceRemove,
    currentValue,
    isInheritField,
    isInherited,
    labelClasses,
    updateCurrentValue,
    updateValue,
    toggleInheritance,
    restoreInheritance,
    removeInheritance,
});
</script>
