<template>
    <ct-block name="ct_form_field_renderer">
        <mt-radio-group-root
            v-if="isRadioGroup"
            v-bind="radioGroupBind"
            ref="component"
            v-model="currentValue"
            class="ct-form-field-renderer"
            :error="error"
        >
            <mt-radio-group-list>
                <mt-radio-group-item
                    v-for="(option, index) in radioOptions"
                    :id="`radio-option-${index}`"
                    :key="option.value"
                    :value="option.value"
                    :label="option.label ?? option.name ?? option.value"
                />
            </mt-radio-group-list>
        </mt-radio-group-root>

        <!-- The last 3 event handlers are for meteor components -->
        <component
            :is="componentName"
            v-else
            v-bind="bind"
            ref="component"
            v-model:[componentPropName]="currentValue"
            class="ct-form-field-renderer"
            :error="error"
            @update:value="emitUpdate"
            @update:model-value="emitUpdate"
            @update:ids="emitUpdate"
            @update:entity-collection="emitUpdate"
        >
            <!-- eslint-disable vue/v-slot-style -->
            <template v-for="(slot, slotName) in getScopedSlots()" #[slotName]="slotData">
                <!-- eslint-enable vue/v-slot-style -->
                <slot :name="slotName" v-bind="slotData"> </slot>
            </template>

            <ct-block name="ct_form_field_renderer_inner">
                <slot>
                    <ct-block name="ct_form_field_renderer_inner_slot"></ct-block>
                </slot>
            </ct-block>
        </component>
    </ct-block>
</template>

<script setup>
defineOptions({ inheritAttrs: false });

const props = defineProps({
    type: {
        type: String,
        required: false,
        default: null,
    },
    config: {
        type: Object,
        required: false,
        default: null,
    },
    value: {
        type: [
            String,
            Number,
            Boolean,
            Array,
            Object,
        ],
        required: false,
        default: null,
    },
    error: {
        type: Object,
        required: false,
        default: null,
    },
});
const emit = defineEmits(['update:value']);

import { ref, computed, watch, useSlots, useAttrs } from 'vue';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';

const slots = useSlots();
const attrs = useAttrs();
const { getInlineSnippet } = useInlineSnippet();

const ctFieldConfig = ref({});
const currentValue = ref(props.value);

const bind = computed(() => {
    let bind = {};

    // Filter all listeners from the $attrs object
    Object.keys(attrs).forEach((key) => {
        if (!['onUpdate:value'].includes(key)) {
            bind[key] = attrs[key];
        }
    });

    bind = {
        ...bind,
        ...props.config,
        ...ctFieldType.value,
        ...translations.value,
        ...optionTranslations.value,
    };

    if (componentName.value === 'mt-entity-select') {
        bind.entity = props.config.entity;
    }

    if (props.type === 'multi-select' || props.type === 'multi-entity-id-select') {
        bind.enableMultiSelection = true;
    }

    return bind;
});
const hasConfig = computed(() => {
    return !!props.config;
});
const componentName = computed(() => {
    if (hasConfig.value) {
        // Handle old "ct-field" component with custom type
        if (props.config.componentName === 'ct-field') {
            return getComponentFromType(props.config.type);
        }

        return props.config.componentName || getComponentFromType();
    }
    return getComponentFromType();
});
const ctFieldType = computed(() => {
    if (hasConfig.value && props.config.hasOwnProperty('type')) {
        return {};
    }

    if (props.type === 'int') {
        return { type: 'number', numberType: 'int' };
    }

    if (props.type === 'float') {
        return { type: 'number', numberType: 'float' };
    }

    if (props.type === 'string' || props.type === 'text') {
        return { type: 'text' };
    }

    if (props.type === 'bool') {
        return { type: 'switch', bordered: true };
    }

    if (props.type === 'datetime') {
        return { type: 'date', dateType: 'datetime' };
    }

    if (props.type === 'date') {
        return { type: 'date', dateType: 'date' };
    }

    if (props.type === 'time') {
        return { type: 'date', dateType: 'time' };
    }

    return { type: props.type };
});
const translations = computed(() => {
    return getTranslations(componentName.value);
});
const optionTranslations = computed(() => {
    if (componentName.value === 'mt-select' || componentName.value === 'mt-radio-group-root') {
        if (!props.config.hasOwnProperty('options')) {
            return {};
        }

        const options = [];
        let labelProperty = 'label';

        // Use custom label property if defined
        if (props.config.hasOwnProperty('labelProperty')) {
            labelProperty = props.config.labelProperty;
        }

        props.config.options.forEach((option) => {
            const translation = getTranslations('options', option, [labelProperty]);
            if (!translation.label) {
                translation.label = option.value;
            }
            // Merge original option with translation
            const translatedOption = { ...option, ...translation };
            options.push(translatedOption);
        });

        return { options };
    }

    return {};
});
const componentPropName = computed(() => {
    if (componentName.value.startsWith('mt-')) {
        return 'modelValue';
    }

    return 'value';
});
const isRadioGroup = computed(() => componentName.value === 'mt-radio-group-root');
const radioGroupBind = computed(() => {
    const { ...radioBind } = bind.value;

    return radioBind;
});
const radioOptions = computed(() => bind.value.options ?? []);

const emitUpdate = (data) => {
    emit('update:value', data);
};
function getTranslations(
    componentName,
    config = props.config,
    translatableFields = [
        'label',
        'placeholder',
        'helpText',
    ],
) {
    if (!translatableFields) {
        return {};
    }
    const translations = {};
    translatableFields.forEach((field) => {
        if (config[field] && config[field] !== '') {
            translations[field] = getInlineSnippet(config[field]);
        }
    });
    return translations;
}
function getComponentFromType(customType = undefined) {
    const type = customType ?? props.type;
    const components = {
        bool: 'mt-switch',
        switch: 'mt-switch',
        textarea: 'mt-textarea',
        checkbox: 'mt-checkbox',
        colorpicker: 'mt-colorpicker',
        compactColorpicker: 'mt-colorpicker',
        date: 'mt-datepicker',
        datetime: 'mt-datepicker',
        time: 'mt-datepicker',
        email: 'mt-email-field',
        float: 'mt-number-field',
        int: 'mt-number-field',
        number: 'mt-number-field',
        'multi-entity-id-select': 'mt-entity-select',
        'multi-select': 'mt-select',
        password: 'mt-password-field',
        radio: 'mt-radio-group-root',
        'single-entity-id-select': 'ct-entity-single-select',
        'single-select': 'mt-select',
        string: 'mt-text-field',
        text: 'mt-text-field',
        tagged: 'ct-tagged-field',
        url: 'mt-url-field',
    };
    return components[type] ?? 'mt-text-field';
}
const getScopedSlots = () => {
    return slots;
};

watch(
    () => currentValue.value,
    (value) => {
        if (
            Array.isArray(value) &&
            Array.isArray(props.value) &&
            value.length === props.value.length &&
            value.every((val, index) => val === props.value[index])
        ) {
            return;
        }

        if (value !== props.value) {
            emit('update:value', value);
        }
    },
    { deep: true },
);
watch(
    () => props.value,
    () => {
        currentValue.value = props.value;
    },
);

ctDefinePublic({
    ctFieldConfig,
    currentValue,
    bind,
    hasConfig,
    componentName,
    ctFieldType,
    translations,
    optionTranslations,
    isRadioGroup,
    radioGroupBind,
    radioOptions,
    componentPropName,
    emitUpdate,
    getTranslations,
    getComponentFromType,
    getScopedSlots,
});

defineExpose({
    ctFieldConfig,
    currentValue,
    bind,
    hasConfig,
    componentName,
    ctFieldType,
    translations,
    optionTranslations,
    isRadioGroup,
    radioGroupBind,
    radioOptions,
    componentPropName,
    emitUpdate,
    getTranslations,
    getComponentFromType,
    getScopedSlots,
});
</script>
