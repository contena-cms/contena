<template>
    <ct-block name="sw_gtc_checkbox">
        <div class="ct-gtc-checkbox">
            <ct-block name="sw_gtc_checkbox_input">
                <mt-checkbox :checked="value" @update:checked="onChange">
                    <template #label>
                        {{ $t('global.ct-gtc-checkbox.label') }}
                        <mt-link type="external" as="a" :href="$t('global.ct-gtc-checkbox.url')">
                            {{ $t('global.ct-gtc-checkbox.link') }}
                        </mt-link>
                    </template>
                </mt-checkbox>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
defineProps({
    value: {
        type: Boolean,
        required: true,
    },
});
const emit = defineEmits(['update:value']);

import { inject } from 'vue';

const feature = inject('feature');

const onChange = (value) => {
    emit('update:value', value);
};

swDefinePublic({
    feature,
    onChange,
});

defineExpose({
    feature,
    onChange,
});
</script>
