<template>
    <ct-block name="sw_data_dictionary_select">
        <ct-block name="sw_data_dictionary_select_input">
            <mt-select
                v-bind="$attrs"
                :model-value="modelValue"
                :options="options"
                :is-loading="isLoading"
                @update:model-value="onUpdateModelValue"
            />
        </ct-block>
    </ct-block>
</template>

<script setup lang="ts">
import { inject, onMounted, ref, watch } from 'vue';
import type DataDictionaryService from 'src/app/service/data-dictionary.service';
import type { DataDictionaryOption } from 'src/app/service/data-dictionary.service';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        technicalName: string;
        modelValue?: string | null;
        activeOnly?: boolean;
    }>(),
    {
        modelValue: null,
        activeOnly: true,
    },
);

const emit = defineEmits(['update:modelValue']);

const service = inject<DataDictionaryService>('dataDictionaryService');
const options = ref<DataDictionaryOption[]>([]);
const isLoading = ref(false);

const load = async (): Promise<void> => {
    if (!service || !props.technicalName) {
        options.value = [];
        return;
    }

    isLoading.value = true;
    try {
        options.value = await service.getOptions(props.technicalName, props.activeOnly);
    } finally {
        isLoading.value = false;
    }
};
const onUpdateModelValue = (value: string | null): void => {
    emit('update:modelValue', value);
};

onMounted(load);
watch(
    () => [
        props.technicalName,
        props.activeOnly,
    ],
    load,
);

swDefinePublic({
    service,
    options,
    isLoading,
    load,
    onUpdateModelValue,
});

defineExpose({
    service,
    options,
    isLoading,
    load,
    onUpdateModelValue,
});
</script>
