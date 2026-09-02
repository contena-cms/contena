<template>
    <ct-block name="ct_context_menu_item">
        <ct-block name="ct_context_menu_item_router_link">
            <router-link
                v-if="routerLink"
                :to="routerLink"
                class="ct-context-menu-item"
                :class="contextMenuItemStyles"
                :disabled="disabled"
                :event="disabled ? null : 'click'"
                :target="disabled ? null : target"
                v-bind="$attrs"
            >
                <ct-block name="ct_context_menu_item_icon">
                    <slot name="icon">
                        <ct-block name="ct_context_menu_item_slot_icon">
                            <mt-icon v-if="icon" :name="icon" size="16px" />
                        </ct-block>
                    </slot>
                </ct-block>

                <ct-block name="ct_context_menu_item_text">
                    <span class="ct-context-menu-item__text" :class="{ 'is--disabled': disabled }">
                        <slot>
                            <ct-block name="ct_context_menu_item_slot_default"></ct-block>
                        </slot>
                    </span>
                </ct-block>
            </router-link>
        </ct-block>

        <ct-block name="ct_context_menu_item_entry">
            <template v-if="routerLink"><!-- Keeps the conditional chain connected across ct-block. --></template>
            <button
                v-else
                class="ct-context-menu-item"
                :class="contextMenuItemStyles"
                v-bind="$attrs"
                @click.capture="handleClick"
            >
                <ct-block name="ct_context_menu_item_entry_icon">
                    <slot name="icon">
                        <ct-block name="ct_context_menu_item_entry_slot_icon">
                            <mt-icon v-if="icon" :name="icon" size="16px" />
                        </ct-block>
                    </slot>
                </ct-block>

                <ct-block name="ct_context_menu_item_entry_text">
                    <span class="ct-context-menu-item__text" :class="{ 'is--disabled': disabled }">
                        <slot>
                            <ct-block name="ct_context_menu_item_entry_slot_default"></ct-block>
                        </slot>
                    </span>
                </ct-block>
            </button>
        </ct-block>
    </ct-block>
</template>

<script setup>
import './ct-context-menu-item.scss';

const props = defineProps({
    icon: {
        type: String,
        required: false,
        default: null,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    routerLink: {
        type: Object,
        required: false,
        default: null,
    },

    target: {
        type: String,
        required: false,
        default: null,
    },

    variant: {
        type: String,
        required: false,
        default: '',
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'success',
                'danger',
                'warning',
                'headline',
            ].includes(value);
        },
    },
});

import { computed } from 'vue';

const contextMenuItemStyles = computed(() => {
    return {
        [`ct-context-menu-item--${props.variant}`]: props.variant,
        'is--disabled': props.disabled && props.variant !== 'headline',
        'ct-context-menu-item--icon': props.icon,
    };
});

const handleClick = (event) => {
    if (props.disabled) {
        event.stopPropagation();
    }
};

ctDefinePublic({
    contextMenuItemStyles,
    handleClick,
});

defineExpose({
    contextMenuItemStyles,
    handleClick,
});
</script>
