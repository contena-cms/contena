<template>
    <ct-block name="ct_context_button">
        <button
            ref="ctContextButton"
            class="ct-context-button"
            :class="contextClass"
            :aria-label="ariaLabel && $t(ariaLabel)"
            @click="onClickButton"
            @keydown.enter="onClickButton"
        >
            <ct-block name="ct_context_button_button">
                <slot name="button">
                    <div class="ct-context-button__button" :class="contextButtonClass" tabindex="-1">
                        <mt-icon :name="icon" :size="iconSize" decorative />
                    </div>
                </slot>
            </ct-block>

            <ct-block name="ct_context_button_menu">
                <mt-floating-ui
                    v-if="showMenu"
                    class="ct-context-button__menu-popover"
                    :is-opened="true"
                    :anchor-element="$refs.ctContextButton"
                    :floating-ui-options="floatingUiOptions"
                    :offset="8"
                    detached
                >
                    <ct-context-menu ref="ctContextMenuRef" :class="contextMenuClass" :style="menuStyles">
                        <slot>
                            <ct-block name="ct_context_button_menu_slot_default"></ct-block>
                        </slot>
                    </ct-context-menu>
                </mt-floating-ui>
            </ct-block>
        </button>
    </ct-block>
</template>

<script setup>
import './ct-context-button.scss';

const props = defineProps({
    showMenuOnStartup: {
        type: Boolean,
        required: false,
        default: false,
    },

    menuWidth: {
        type: Number,
        required: false,
        default: 220,
    },

    menuHorizontalAlign: {
        type: String,
        required: false,
        default: 'right',
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'right',
                'left',
            ].includes(value);
        },
    },

    menuVerticalAlign: {
        type: String,
        required: false,
        default: 'bottom',
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'bottom',
                'top',
            ].includes(value);
        },
    },

    icon: {
        type: String,
        required: false,
        default: 'solid-ellipsis-h-s',
    },

    iconSize: {
        type: String,
        required: false,
        default: 'var(--scale-size-14)',
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    autoClose: {
        type: Boolean,
        required: false,
        default: true,
    },

    autoCloseOutsideClick: {
        type: Boolean,
        required: false,
        default: false,
    },

    additionalContextMenuClasses: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },

    zIndex: {
        type: Number,
        required: false,
        default: 1100,
    },

    ariaLabel: {
        type: String,
        required: false,
        default: 'ct-context-button.ariaLabel',
    },
});
const emit = defineEmits(['on-open-change']);

import { ref, computed, inject, onBeforeUnmount } from 'vue';

const ctContextButton = ref(null);
const ctContextMenuRef = ref(null);

const feature = inject('feature');

const showMenu = ref(props.showMenuOnStartup);

const menuStyles = computed(() => {
    return {
        width: `${props.menuWidth}px`,
    };
});
const floatingUiOptions = computed(() => {
    const verticalPlacement = props.menuVerticalAlign === 'top' ? 'top' : 'bottom';
    const horizontalPlacement = props.menuHorizontalAlign === 'left' ? 'start' : 'end';

    return {
        placement: `${verticalPlacement}-${horizontalPlacement}`,
    };
});
const contextClass = computed(() => {
    return {
        'is--disabled': props.disabled,
        'is--active': showMenu.value,
    };
});
const contextButtonClass = computed(() => {
    return {
        'is--active': showMenu.value,
    };
});
const contextMenuClass = computed(() => {
    return {
        'is--left-align': props.menuHorizontalAlign === 'left',
        'is--top-align': props.menuVerticalAlign === 'top',
        ...props.additionalContextMenuClasses,
    };
});

const beforeUnmountComponent = () => {
    removeClickEventListeners();
};
const onClickButton = () => {
    if (props.disabled) {
        return;
    }

    if (showMenu.value) {
        closeMenu();
    } else {
        openMenu();
    }
};
function openMenu() {
    emit('on-open-change', true);
    showMenu.value = true;
    if (props.autoCloseOutsideClick) {
        document.addEventListener('click', handleOutsideClickEvent, true);
    }
    document.addEventListener('click', handleClickEvent);
}
function handleOutsideClickEvent(event) {
    if (!showMenu.value) {
        return;
    }
    const clickedInsideButton = ctContextButton.value?.contains(event.target) ?? false;
    const clickedInsideMenu = event.target instanceof Element && event.target.closest('.ct-context-menu') !== null;
    if (!clickedInsideButton && !clickedInsideMenu) {
        closeMenu();
    }
}
function handleClickEvent(event) {
    // when target is disabled dont close the context menu item
    const isTargetDisabled = event && event.target.classList.contains('is--disabled');
    if (isTargetDisabled) {
        return false;
    }

    // close menu when no context button exists (when component gets destroyed)
    const contextButton = ctContextButton.value;
    if (!contextButton) {
        return closeMenu();
    }

    // check if the user clicked inside the context menu
    const clickedInside = contextButton ? contextButton.contains(event.target) : false;
    if (props.autoCloseOutsideClick && showMenu.value && !clickedInside) {
        const contextMenu = ctContextMenuRef.value.$el;
        const clickedOutside = contextMenu?.contains(event.target) ?? false;
        if (!event?.target || !clickedOutside) {
            return closeMenu();
        }
    }

    // only close the menu on inside clicks if autoclose is active
    const shouldCloseOnInsideClick = props.autoClose && !clickedInside;

    // close menu when there is no native event (when vue event is triggered) or user clicked outside
    if (!event || !event.target || shouldCloseOnInsideClick) {
        return closeMenu();
    }
    return false;
}
function closeMenu() {
    emit('on-open-change', false);
    showMenu.value = false;
    removeClickEventListeners();
}
function removeClickEventListeners() {
    document.removeEventListener('click', handleOutsideClickEvent, true);
    document.removeEventListener('click', handleClickEvent);
}

onBeforeUnmount(() => {
    beforeUnmountComponent();
});

ctDefinePublic({
    feature,
    showMenu,
    menuStyles,
    floatingUiOptions,
    contextClass,
    contextButtonClass,
    contextMenuClass,
    beforeUnmountComponent,
    onClickButton,
    openMenu,
    handleOutsideClickEvent,
    handleClickEvent,
    closeMenu,
    removeClickEventListeners,
});

defineExpose({
    feature,
    showMenu,
    menuStyles,
    floatingUiOptions,
    contextClass,
    contextButtonClass,
    contextMenuClass,
    beforeUnmountComponent,
    onClickButton,
    openMenu,
    handleOutsideClickEvent,
    handleClickEvent,
    closeMenu,
    removeClickEventListeners,
});
</script>
