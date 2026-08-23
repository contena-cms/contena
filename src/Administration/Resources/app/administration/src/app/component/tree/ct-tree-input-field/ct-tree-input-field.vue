<template>
    <ct-block name="sw_tree_input_field">
        <div class="ct-tree-item ct-tree-input-field is--no-children" :class="classes">
            <ct-block name="sw_tree_input_fieldelement">
                <div class="ct-tree-item__element">
                    <ct-block name="sw_tree_input_fieldelement_grip">
                        <div class="ct-tree-item__icon">
                            <mt-icon v-if="!disabled" name="regular-circle-xxs" size="18" />
                        </div>
                    </ct-block>

                    <ct-block name="sw_tree_input_fieldelement_content">
                        <div class="ct-tree-item__content">
                            <slot name="content">
                                <ct-block name="sw_tree_input_fieldslot_content">
                                    <ct-confirm-field
                                        :value="currentValue"
                                        :disabled="disabled"
                                        :placeholder="$t('ct-tree.general.buttonCreate')"
                                        @input="createNewItem"
                                    />
                                </ct-block>
                            </slot>

                            <span v-if="disabled" class="ct-tree-input-field__language-warning">
                                {{ $t('ct-tree.general.actions.actionsDisabledInLanguage') }}.
                            </span>
                        </div>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-tree-input-field.scss';

const props = defineProps({
    currentValue: {
        type: String,
        required: false,
    },

    disabled: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits(['new-item-create']);

import { computed } from 'vue';

const classes = computed(() => {
    return {
        'is--disabled': props.disabled,
    };
});

const createNewItem = (itemName) => {
    emit('new-item-create', itemName);
};

swDefinePublic({
    classes,
    createNewItem,
});

defineExpose({
    classes,
    createNewItem,
});
</script>
