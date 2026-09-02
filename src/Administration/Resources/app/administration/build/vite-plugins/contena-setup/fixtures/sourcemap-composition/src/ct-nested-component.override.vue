<template>
    <ct-block extends="ct_example_card">
        <p>{{ headline }}</p>
    </ct-block>
</template>
<script setup>
import { computed } from 'vue';

const previousState = useCtPreviousState();

const headline = computed(() => `${previousState.headline.value} and the override`);

ctDefineOverride({
    headline,
});
</script>
