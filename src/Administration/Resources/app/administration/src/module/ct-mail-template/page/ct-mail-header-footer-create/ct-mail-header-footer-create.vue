<template>
    <ct-mail-header-footer-detail />
</template>

<script setup lang="ts">
import { onBeforeMount } from 'vue';

defineProps({});
function resetLanguage(): void {
    const context = Contena.Store.get('context');
    if (!context.isSystemDefaultLanguage) {
        context.resetLanguageToDefault();
    }
}

onBeforeMount(resetLanguage);

swDefinePublic({
    resetLanguage,
});

defineExpose({ resetLanguage });
</script>
