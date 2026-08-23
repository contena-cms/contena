<template>
    <ct-block name="sw_example_card">
        <p>{{ headline }}</p>
    </ct-block>
</template>
<script setup>
import { computed } from 'vue';

const headline = computed(() => 'Hello from source');

swDefinePublic({
    headline,
});
</script>
