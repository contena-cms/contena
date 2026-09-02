<template>
    <ct-mail-template-detail />
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

ctDefinePublic({
    resetLanguage,
});

defineExpose({ resetLanguage });
</script>
