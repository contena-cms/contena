<template>
    <ct-block name="sw_select_result">
        <!--  eslint-disable-next-line vuejs-accessibility/mouse-events-have-key-events vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
        <li
            ref="resultElement"
            v-tooltip="tooltip"
            class="ct-select-result"
            :class="resultClasses"
            :aria-selected="active ? 'true' : 'false'"
            :aria-label="ariaLabel"
            tabindex="0"
            @mouseenter="onMouseEnter"
            @click.stop="onClickResult"
        >
            <ct-block name="sw_select_result_item_preview">
                <span class="ct-select-result__result-item-preview">
                    <slot name="preview"></slot>
                </span>
            </ct-block>

            <ct-block name="sw_select_result_item_text_holder">
                <span class="ct-select-result__result-item-text">
                    <ct-block name="sw_select_result_item_text">
                        <slot></slot>
                    </ct-block>
                </span>
            </ct-block>

            <ct-block name="sw_select_result_item_icon_transition">
                <transition name="ct-select-result-appear">
                    <ct-block name="sw_select_result_item_icon">
                        <mt-icon
                            v-if="selected"
                            class="ct-select-result__result-item-checkmark"
                            name="regular-checkmark-xs"
                        />
                    </ct-block>
                </transition>
            </ct-block>

            <ct-block name="sw_select_result_item_description_holder">
                <span v-if="hasDescriptionSlot" class="ct-select-result__result-item-description">
                    <ct-block name="sw_select_result_item_description">
                        <slot name="description"></slot>
                    </ct-block>
                </span>
            </ct-block>
        </li>
    </ct-block>
</template>

<script setup>
import './ct-select-result.scss';

const props = defineProps({
    index: {
        type: Number,
        required: true,
    },
    item: {
        type: Object,
        required: true,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    selected: {
        type: Boolean,
        required: false,
        default: false,
    },
    descriptionPosition: {
        type: String,
        required: false,
        default: 'right',
        validValues: [
            'bottom',
            'right',
            'left',
        ],
        validator(value) {
            return [
                'bottom',
                'right',
                'left',
            ].includes(value);
        },
    },
    ariaLabel: {
        type: String,
        required: false,
        default: undefined,
    },
    tooltip: {
        type: Object,
        required: false,
        default: () => ({ disabled: true }),
    },
});

import { ref, computed, inject, useSlots, onUnmounted } from 'vue';

const slots = useSlots();
const resultElement = ref(null);

const setActiveItemIndex = inject('setActiveItemIndex', () => {});
const feature = inject('feature');

const active = ref(false);

const resultClasses = computed(() => {
    return [
        {
            'is--active': active.value,
            'is--disabled': props.disabled,
            'has--description': hasDescriptionSlot.value,
            [`is--description-${props.descriptionPosition}`]: hasDescriptionSlot.value,
        },
        `ct-select-option--${props.index}`,
    ];
});
const hasDescriptionSlot = computed(() => {
    return !!slots.description;
});

const createdComponent = () => {
    Contena.Utils.EventBus.on('active-item-change', checkIfActive);
    Contena.Utils.EventBus.on('item-select-by-keyboard', checkIfSelected);
};
const destroyedComponent = () => {
    Contena.Utils.EventBus.off('active-item-change', checkIfActive);
    Contena.Utils.EventBus.off('item-select-by-keyboard', checkIfSelected);
};
function checkIfSelected(selectedItemIndex) {
    if (selectedItemIndex === props.index) onClickResult({});
}
function checkIfActive(
    activeItemIndex,
    { shouldFocus } = {
        shouldFocus: false,
    },
) {
    active.value = props.index === activeItemIndex;
    if (active.value && shouldFocus) {
        resultElement.value?.focus();
    }
}
function onClickResult() {
    if (props.disabled) {
        return;
    }
    Contena.Utils.EventBus.emit('item-select', props.item);
}
const onMouseEnter = () => {
    setActiveItemIndex(props.index);
};

createdComponent();

onUnmounted(() => {
    destroyedComponent();
});

swDefinePublic({
    setActiveItemIndex,
    feature,
    active,
    resultClasses,
    hasDescriptionSlot,
    createdComponent,
    destroyedComponent,
    checkIfSelected,
    checkIfActive,
    onClickResult,
    onMouseEnter,
});

defineExpose({
    setActiveItemIndex,
    feature,
    active,
    resultClasses,
    hasDescriptionSlot,
    createdComponent,
    destroyedComponent,
    checkIfSelected,
    checkIfActive,
    onClickResult,
    onMouseEnter,
});
</script>
