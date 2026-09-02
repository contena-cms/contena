<template>
    <ct-block name="ct_select_base">
        <ct-block-field class="ct-select" :class="ctFieldClasses" v-bind="$attrs" :disabled="disabled" :size="size">
            <template #ct-field-input="{ identification, error, disabled, size }">
                <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
                <div
                    ref="selectWrapper"
                    class="ct-select__selection"
                    tabindex="0"
                    :aria-expanded="expanded ? 'true' : 'false'"
                    @click="expand"
                    @focus="expand"
                    @keydown.tab="collapse"
                    @keydown.esc="collapse"
                >
                    <slot
                        name="ct-select-selection"
                        v-bind="{ identification, error, disabled, size, expand, collapse }"
                    ></slot>
                </div>
                <div class="ct-select__selection-indicators">
                    <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                    <mt-loader v-if="isLoading" class="ct-select__select-indicator" size="16px" />

                    <button
                        v-if="!disabled && isClearable"
                        class="ct-select__select-indicator-hitbox"
                        data-clearable-button
                        :aria-label="$t('global.ct-select-base.buttonClear')"
                        @click.prevent.stop="emitClear"
                        @keydown.tab.stop="focusParentSelect"
                    >
                        <mt-icon
                            class="ct-select__select-indicator ct-select__select-indicator-clear"
                            name="regular-times-s"
                            size="var(--scale-size-16)"
                            color="var(--color-icon-primary-default)"
                        />
                    </button>

                    <mt-icon
                        class="ct-select__select-indicator ct-select__select-indicator-expand"
                        :class="{ 'ct-select__select-indicator-expand--rotated': !expanded }"
                        name="regular-chevron-up-xs"
                        color="var(--color-icon-primary-default)"
                        size="var(--scale-size-10)"
                        @click="toggleExpand"
                    />
                </div>

                <template v-if="expanded">
                    <transition name="ct-select-result-list-fade-down" @click.stop>
                        <slot name="results-list" v-bind="{ collapse }"></slot>
                    </transition>
                </template>
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
import './ct-select-base.scss';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    /**
     * Controls visibility of the clear button.
     * When undefined, defaults to true if not required, false if required.
     * Explicit true/false overrides this default behavior.
     * @see isClearable computed property
     */
    showClearableButton: {
        type: Boolean,
        required: false,
        default: undefined,
    },

    size: {
        type: String,
        required: false,
        default: 'default',
    },
});
const emit = defineEmits([
    'select-expanded',
    'select-collapsed',
    'clear',
]);

import { ref, computed, provide, useAttrs, onMounted as vueOnMounted, onBeforeUnmount as vueOnBeforeUnmount } from 'vue';

const attrs = useAttrs();

const $attrs = attrs;
const selectWrapper = ref(null);
const selectBaseRoot = computed(() => selectWrapper.value?.closest('.ct-select'));

provide('selectBaseRoot', selectBaseRoot);

const expanded = ref(false);

const ctFieldClasses = computed(() => {
    return { 'has--focus': expanded.value };
});
const isClearable = computed(() => {
    // If explicitly set, use the provided value
    if (props.showClearableButton !== undefined) {
        return props.showClearableButton;
    }

    // Default: clearable when not required
    // '' case is for empty attribute like <form-field required> which should be treated as true
    return !attrs.required && attrs.required !== '';
});

const onMounted = () => {
    document.addEventListener('keydown', handleKeydown);
};
const onBeforeUnmount = () => {
    document.removeEventListener('keydown', handleKeydown);
};
function handleKeydown(event) {
    if (!expanded.value) {
        return;
    }

    // Handle escape key
    if (event.key === 'Escape' || event.key === 'Esc') {
        collapse();
    }
}
const toggleExpand = () => {
    if (!expanded.value) {
        expand();
    } else {
        collapse();
    }
};
function expand() {
    if (expanded.value) {
        return;
    }
    if (props.disabled) {
        return;
    }
    expanded.value = true;
    document.addEventListener('click', listenToClickOutside);
    emit('select-expanded');
}
function collapse(event) {
    document.removeEventListener('click', listenToClickOutside);
    expanded.value = false;

    // do not let clearable button trigger change event
    if (event?.target?.dataset.clearableButton === undefined) {
        emit('select-collapsed');
    }

    // @see NEXT-16079 allow back tab-ing through form via SHIFT+TAB
    if (event && event?.shiftKey) {
        event.preventDefault();
        focusPreviousFormElement();
    }
}
function focusPreviousFormElement() {
    const focusableSelector = 'a, button, input, textarea, select, details, [tabindex]:not([tabindex="-1"])';
    const myFocusable = selectBaseRoot.value?.querySelector(focusableSelector);
    const keyboardFocusable = [...document.querySelectorAll(focusableSelector)].filter(
        (el) => !el.hasAttribute('disabled') && el.dataset.clearableButton === undefined,
    );
    keyboardFocusable.forEach((element, index) => {
        if (index > 0 && element === myFocusable) {
            const kbFocusable = keyboardFocusable[index - 1];
            kbFocusable.click();
            kbFocusable.focus();
        }
    });
}
function listenToClickOutside(event) {
    const target = event.target;
    const clickIsInsideSelect = target instanceof Node && selectBaseRoot.value?.contains(target);

    // Borderline clicks can target the body even while the pointer is still over the select.
    // Non-layout environments like jsdom do not implement the hit-test fallback.
    const clickedElementStackContainsSelect =
        typeof document.elementsFromPoint === 'function' &&
        document
            .elementsFromPoint(event.clientX, event.clientY)
            .some((element) => element === selectBaseRoot.value || selectBaseRoot.value?.contains(element));
    if (!clickIsInsideSelect && !clickedElementStackContainsSelect) {
        collapse();
    }
}
const emitClear = () => {
    emit('clear');
};
const focusParentSelect = (event) => {
    if (event && event?.shiftKey) {
        selectWrapper.value.click();
        event.preventDefault();
    }
};

vueOnMounted(() => {
    onMounted();
});
vueOnBeforeUnmount(() => {
    onBeforeUnmount();
});

ctDefinePublic({
    expanded,
    ctFieldClasses,
    isClearable,
    onMounted,
    onBeforeUnmount,
    handleKeydown,
    toggleExpand,
    expand,
    collapse,
    focusPreviousFormElement,
    listenToClickOutside,
    emitClear,
    focusParentSelect,
});

defineExpose({
    expanded,
    ctFieldClasses,
    isClearable,
    onMounted,
    onBeforeUnmount,
    handleKeydown,
    toggleExpand,
    expand,
    collapse,
    focusPreviousFormElement,
    listenToClickOutside,
    emitClear,
    focusParentSelect,
});
</script>
