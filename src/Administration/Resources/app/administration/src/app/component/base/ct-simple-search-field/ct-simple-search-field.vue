<template>
    <ct-block name="sw_simple_search_field">
        <div class="ct-simple-search-field">
            <ct-block name="sw_simple_search_field_input">
                <mt-text-field
                    class="ct-simple-search-field__input"
                    :class="fieldClasses"
                    :placeholder="placeholder"
                    v-bind="$attrs"
                    :model-value="value"
                    :size="size"
                    @update:model-value="onInput"
                />
            </ct-block>

            <ct-block name="sw_simple_search_field_search_icon">
                <slot name="ct-simple-search-field-icon">
                    <ct-block name="sw_simple_search_field_slot_search_icon">
                        <mt-icon v-if="icon" class="ct-simple-search-field__search-icon" :name="icon" size="16px" />
                    </ct-block>
                </slot>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-simple-search-field.scss';
const { Utils } = Contena;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: {
        type: String,
        required: false,
        default: 'default',
        validValues: [
            'default',
            'inverted',
            'form',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'default',
                'inverted',
                'form',
            ].includes(value);
        },
    },

    value: {
        type: String,
        default: null,
        required: false,
    },

    size: {
        type: String,
        required: false,
        default: 'default',
    },

    delay: {
        type: Number,
        required: false,
        default: 400,
    },

    icon: {
        type: String,
        required: false,
        default: 'regular-search-s',
    },
});
const emit = defineEmits([
    'update:value',
    'search-term-change',
]);

import { ref, computed, useAttrs } from 'vue';
import { useI18n } from 'vue-i18n';

const attrs = useAttrs();
const { t } = useI18n();

const onSearchTermChanged = ref(
    Utils.debounce(function debounceInput(input) {
        emit('search-term-change', input);
    }, props.delay),
);

const fieldClasses = computed(() => {
    return [
        `ct-simple-search-field--${props.variant}`,
    ];
});
const placeholder = computed(() => {
    return attrs.placeholder || t('global.ct-simple-search-field.defaultPlaceholder');
});

const onInput = (input) => {
    emit('update:value', input);
    onSearchTermChanged.value(input);
};

swDefinePublic({
    onSearchTermChanged,
    fieldClasses,
    placeholder,
    onInput,
});

defineExpose({
    onSearchTermChanged,
    fieldClasses,
    placeholder,
    onInput,
});
</script>
