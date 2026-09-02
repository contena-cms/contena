<template>
    <ct-block name="ct_select_selection_list">
        <ul class="ct-select-selection-list">
            <ct-block name="ct_select_selection_list_item">
                <template v-if="!hideLabels">
                    <li
                        v-for="(selection, index) in selections"
                        :key="selection[valueProperty]"
                        class="ct-select-selection-list__item-holder"
                        :class="'ct-select-selection-list__item-holder--' + index"
                        :data-id="selection[valueProperty]"
                    >
                        <ct-block name="ct_select_selection_list_item_inner">
                            <slot
                                name="selected-option"
                                v-bind="{ selection, defaultLabel: selection[labelProperty], disabled }"
                            >
                                <ct-label
                                    :dismissable="!isSelectionDisabled(selection)"
                                    :size="size"
                                    @dismiss="onClickDismiss(selection)"
                                >
                                    <ct-block name="ct_select_selection_list_item_text">
                                        <span class="ct-select-selection-list__item">
                                            <slot
                                                name="label-property"
                                                v-bind="{ item: selection, index, labelProperty, valueProperty }"
                                            >
                                                {{ selection[labelProperty] }}
                                            </slot>
                                        </span>
                                    </ct-block>
                                </ct-label>
                            </slot>
                        </ct-block>
                    </li>
                </template>
            </ct-block>

            <ct-block name="ct_select_selection_list_load_more">
                <li v-if="invisibleCount > 0 && !hideLabels" class="ct-select-selection-list__load-more">
                    <slot name="invisible-count" v-bind="{ invisibleCount, onClickInvisibleCount }">
                        <ct-block name="ct_select_selection_list_load_more_item_button">
                            <mt-button
                                class="ct-select-selection-list__load-more-button"
                                variant="secondary"
                                @click.stop="onClickInvisibleCount"
                            >
                                +{{ invisibleCount }}
                            </mt-button>
                        </ct-block>
                    </slot>
                </li>
            </ct-block>

            <ct-block name="ct_select_selection_list_input">
                <li class="ct-select-selection-list__input-wrapper">
                    <slot name="input" v-bind="{ placeholder, searchTerm, onSearchTermChange, onKeyDownDelete }">
                        <!-- eslint-disable-next-line vuejs-accessibility/role-has-required-aria-props -->
                        <input
                            ref="ctSelectInput"
                            class="ct-select-selection-list__input"
                            type="text"
                            role="combobox"
                            :disabled="disabled"
                            :readonly="!enableSearch"
                            :placeholder="showPlaceholder"
                            :value="searchTerm"
                            :aria-label="inputLabel"
                            :autocomplete="autocomplete"
                            @input="onSearchTermChange"
                            @keydown.delete="onKeyDownDelete"
                            @keydown.enter="onKeyDownEnter"
                        />
                    </slot>
                </li>
            </ct-block>
        </ul>
    </ct-block>
</template>

<script setup>
import './ct-select-selection-list.scss';

const props = defineProps({
    selections: {
        type: Array,
        required: false,
        default: () => [],
    },
    labelProperty: {
        type: String,
        required: false,
        default: 'label',
    },
    valueProperty: {
        type: String,
        required: false,
        default: 'value',
    },
    enableSearch: {
        type: Boolean,
        required: false,
        default: true,
    },
    invisibleCount: {
        type: Number,
        required: false,
        default: 0,
    },
    size: {
        type: String,
        required: false,
        default: null,
    },
    alwaysShowPlaceholder: {
        type: Boolean,
        required: false,
        default: false,
    },
    placeholder: {
        type: String,
        required: false,
        default: '',
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
    searchTerm: {
        type: String,
        required: false,
        default: '',
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    selectionDisablingMethod: {
        type: Function,
        required: false,
        default: () => false,
    },
    hideLabels: {
        type: Boolean,
        required: false,
        default: false,
    },
    inputLabel: {
        type: String,
        required: false,
        default: undefined,
    },
    autocomplete: {
        type: String,
        required: false,
        default: undefined,
    },
});
const emit = defineEmits([
    'total-count-click',
    'search-term-change',
    'last-item-delete',
    'key-down-enter',
    'item-remove',
]);

import { ref, computed, inject } from 'vue';

const ctSelectInput = ref(null);

const feature = inject('feature');

const showPlaceholder = computed(() => {
    return props.alwaysShowPlaceholder || props.selections.length === 0 || props.hideLabels ? props.placeholder : '';
});

const isSelectionDisabled = (selection) => {
    if (props.disabled) {
        return true;
    }

    return props.selectionDisablingMethod(selection);
};
const onClickInvisibleCount = () => {
    emit('total-count-click');
};
const onSearchTermChange = (event) => {
    emit('search-term-change', event.target.value, event);
};
const onKeyDownDelete = () => {
    if (props.searchTerm.length < 1) {
        emit('last-item-delete');
    }
};
const onKeyDownEnter = () => {
    emit('key-down-enter');
};
const onClickDismiss = (item) => {
    emit('item-remove', item);
};
const focus = () => {
    ctSelectInput.value.focus();
};
const blur = () => {
    ctSelectInput.value.blur();
};
const select = () => {
    ctSelectInput.value.select();
};
const getFocusEl = () => {
    return ctSelectInput.value;
};

ctDefinePublic({
    feature,
    showPlaceholder,
    isSelectionDisabled,
    onClickInvisibleCount,
    onSearchTermChange,
    onKeyDownDelete,
    onKeyDownEnter,
    onClickDismiss,
    focus,
    blur,
    select,
    getFocusEl,
});

defineExpose({
    feature,
    showPlaceholder,
    isSelectionDisabled,
    onClickInvisibleCount,
    onSearchTermChange,
    onKeyDownDelete,
    onKeyDownEnter,
    onClickDismiss,
    focus,
    blur,
    select,
    getFocusEl,
});
</script>
