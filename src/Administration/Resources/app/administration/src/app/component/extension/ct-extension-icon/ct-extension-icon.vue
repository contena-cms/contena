<template>
    <ct-block name="sw_extension_icon">
        <div class="ct-extension-icon">
            <img class="ct-extension-icon__icon" :draggable="false" :src="src" :alt="alt" />
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-extension-icon.scss';

defineProps({
    src: {
        type: String,
        required: true,
    },
    alt: {
        type: String,
        required: false,
        default: '',
    },
});

swDefinePublic({});
</script>
