<template>
    <ct-block name="sw_multi_tag_select">
        <ct-select-base
            class="ct-multi-tag-select"
            :is-loading="isLoading"
            :error="errorObject"
            :disabled="disabled"
            v-bind="$attrs"
            @select-expanded="setDropDown(true)"
            @select-collapsed="setDropDown(false)"
        >
            <template #ct-select-selection="{ size }">
                <ct-block name="sw_multi_tag_select_base">
                    <ct-block name="sw_multi_tag_select_base_selection">
                        <ct-block name="sw_multi_tag_select_base_selection_slot">
                            <ct-select-selection-list
                                ref="selectionList"
                                :selections="visibleValues"
                                :invisible-count="invisibleValueCount"
                                :disabled="disabled"
                                label-property="value"
                                value-property="value"
                                v-bind="{ size, placeholder, searchTerm, autocomplete }"
                                @total-count-click="expandValueLimit"
                                @item-remove="remove"
                                @last-item-delete="removeLastItem"
                                @search-term-change="onSearchTermChange"
                                @key-down-enter="onSelectionListKeyDownEnter"
                            >
                                <template #label-property="{ item, index, labelProperty, valueProperty }">
                                    <ct-block name="sw_multi_tag_select_base_selection_list">
                                        <ct-block name="sw_multi_tag_select_base_selection_list_label">
                                            <ct-block name="sw_multi_tag_select_base_selection_list_label_inner">
                                                <slot
                                                    name="selection-label-property"
                                                    v-bind="{ item, index, labelProperty, valueProperty }"
                                                >
                                                    {{ getKey(item, labelProperty) }}
                                                </slot>
                                            </ct-block>
                                        </ct-block>
                                    </ct-block>
                                </template>
                            </ct-select-selection-list>
                        </ct-block>
                    </ct-block>
                </ct-block>
            </template>

            <template #results-list>
                <ct-block name="sw_multi_tag_select_validation">
                    <div v-if="hasFocus" class="ct-multi-tag-select-validation ct-select-result-list">
                        <mt-floating-ui
                            class="ct-select-result-list-popover ct-multi-tag-select-validation-popover"
                            :is-opened="true"
                            :match-reference-width="true"
                        >
                            <div class="ct-select-result-list__content">
                                <ct-block name="sw_multi_tag_select_validation_valid">
                                    <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                                    <div v-if="inputIsValid" class="ct-multi-tag-select-valid" @click="addItem">
                                        <ct-block name="sw_multi_tag_select_validation_valid_message">
                                            <slot name="message-add-data">
                                                <span>{{ $t('global.ct-multi-tag-select.addData') }}</span>
                                            </slot>
                                        </ct-block>
                                    </div>
                                </ct-block>

                                <ct-block name="sw_multi_tag_select_validation_invalid">
                                    <template v-if="inputIsValid"
                                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                                    >
                                    <div v-else class="ct-multi-tag-select-invalid">
                                        <ct-block name="sw_multi_tag_select_validation_invalid_message">
                                            <slot name="message-enter-valid-data">
                                                <span>{{ $t('global.ct-multi-tag-select.enterValidData') }}</span>
                                            </slot>
                                        </ct-block>
                                    </div>
                                </ct-block>

                                <slot name="validation-options" v-bind="{ searchTerm, onSearchTermChange, addItem }"></slot>
                            </div>
                        </mt-floating-ui>
                    </div>
                </ct-block>
            </template>

            <template #label>
                <slot name="label"></slot>
            </template>

            <template #hint>
                <slot name="hint"></slot>
            </template>
        </ct-select-base>
    </ct-block>
</template>

<script setup>
import './ct-multi-tag-select.scss';
const { get } = Contena.Utils;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    value: {
        type: Array,
        required: true,
    },

    valueLimit: {
        type: Number,
        required: false,
        default: 5,
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

    validMessage: {
        type: String,
        required: false,
        default: '',
    },

    invalidMessage: {
        type: String,
        required: false,
        default: '',
    },

    validate: {
        type: Function,
        required: false,
        default: (searchTerm) => searchTerm.length > 0,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    autocomplete: {
        type: String,
        required: false,
        default: undefined,
    },
    errorCode: {
        type: String,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'add-item-is-valid',
    'update:value',
    'display-values-expand',
]);

import { ref, computed } from 'vue';

const selectionList = ref(null);

const searchTerm = ref('');
const hasFocus = ref(false);
const limit = ref(props.valueLimit);

const errorObject = computed(() => {
    const hasError = props.errorCode && !inputIsValid.value && searchTerm.value.length > 0;

    return hasError ? { code: props.errorCode } : null;
});
const inputIsValid = computed(() => {
    return props.validate(searchTerm.value);
});
const visibleValues = computed(() => {
    if (!props.value || props.value.length <= 0) {
        return [];
    }

    return props.value.map((entry) => ({ value: entry })).slice(0, limit.value);
});
const totalValuesCount = computed(() => {
    if (props.value.length) {
        return props.value.length;
    }

    return 0;
});
const invisibleValueCount = computed(() => {
    if (!props.value) {
        return 0;
    }

    return Math.max(0, totalValuesCount.value - limit.value);
});

const onSelectionListKeyDownEnter = () => {
    addItem();
};
function addItem() {
    emit('add-item-is-valid', inputIsValid.value);
    if (!inputIsValid.value) {
        return;
    }
    emit('update:value', [
        ...props.value,
        searchTerm.value,
    ]);
    searchTerm.value = '';
}
const remove = ({ value }) => {
    emit(
        'update:value',
        props.value.filter((entry) => entry !== value),
    );
};
const removeLastItem = () => {
    if (!props.value.length) {
        return;
    }

    if (invisibleValueCount.value > 0) {
        expandValueLimit();
        return;
    }

    emit('update:value', props.value.slice(0, -1));
};
const onSearchTermChange = (term) => {
    searchTerm.value = term;
};
const setDropDown = (open = true) => {
    selectionList.value.focus();
    hasFocus.value = open;

    if (open) {
        return;
    }

    addItem();
};
function expandValueLimit() {
    emit('display-values-expand');
    limit.value += limit.value;
}
const getKey = (item, property, fallback = null) => get(item, property, fallback);

swDefinePublic({
    getKey,
    searchTerm,
    hasFocus,
    limit,
    errorObject,
    inputIsValid,
    visibleValues,
    totalValuesCount,
    invisibleValueCount,
    onSelectionListKeyDownEnter,
    addItem,
    remove,
    removeLastItem,
    onSearchTermChange,
    setDropDown,
    expandValueLimit,
});

defineExpose({
    getKey,
    searchTerm,
    hasFocus,
    limit,
    errorObject,
    inputIsValid,
    visibleValues,
    totalValuesCount,
    invisibleValueCount,
    onSelectionListKeyDownEnter,
    addItem,
    remove,
    removeLastItem,
    onSearchTermChange,
    setDropDown,
    expandValueLimit,
});
</script>
