<template>
    <ct-block name="ct_settings_snippet_filter_switch">
        <div class="ct-settings-snippet-filter-switch" :class="fieldClasses">
            <ct-block name="ct_settings_snippet_filter_switch_field">
                <mt-switch :name="name" :model-value="value" :label="label" @update:model-value="onChange" />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import './ct-settings-snippet-filter-switch.scss';

const props = withDefaults(
    defineProps<{
        label?: string;
        name: string;
        group?: string | null;
        borderTop?: boolean;
        borderBottom?: boolean;
        type?: 'small' | 'large';
        value?: boolean;
    }>(),
    {
        label: '',
        group: null,
        borderTop: false,
        borderBottom: false,
        type: 'small',
        value: false,
    },
);
const emit = defineEmits<{
    'update:value': [field: { value: boolean; name: string; group: string | null }];
}>();

const fieldClasses = computed(() => ({
    'ct-settings-snippet-filter-switch--small': props.type === 'small',
    'ct-settings-snippet-filter-switch--large': props.type === 'large',
    'border-top': props.borderTop,
    'border-bottom': props.borderBottom,
}));
const onChange = (value: boolean): void => {
    emit('update:value', { value, name: props.name, group: props.group });
};

ctDefinePublic({
    fieldClasses,
    onChange,
});

defineExpose({ fieldClasses, onChange });
</script>
