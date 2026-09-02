<template>
    <ct-block name="ct_example_card">
        <p>{{ headline }}</p>
    </ct-block>
</template>
<script setup>
import { computed } from 'vue';

const headline = computed(() => 'Hello from source');

ctDefinePublic({
    headline,
});
</script>
