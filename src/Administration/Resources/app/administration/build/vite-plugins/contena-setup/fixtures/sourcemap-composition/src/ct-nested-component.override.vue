<template>
    <ct-block extends="sw_example_card">
        <p>{{ headline }}</p>
    </ct-block>
</template>
<script setup>
import { computed } from 'vue';

const previousState = useSwPreviousState();

const headline = computed(() => `${previousState.headline.value} and the override`);

swDefineOverride({
    headline,
});
</script>
