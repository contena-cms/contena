<template>
    <!-- v-on="$listeners" needed becaues vue/compat removes them from $attrs -->
    <!-- New mt-loader component -->
    <ct-block name="sw_loader">
        <mt-loader v-bind="$attrs">
            <template v-for="(index, name) in getSlots()" #[name]="data">
                <slot :name="name" v-bind="data"> </slot>
            </template>
            <slot></slot>
        </mt-loader>
    </ct-block>
</template>

<script setup>
defineProps({});

import { useSlots } from 'vue';

const slots = useSlots();

const getSlots = () => {
    return slots;
};

swDefinePublic({
    getSlots,
});

defineExpose({
    getSlots,
});
</script>
