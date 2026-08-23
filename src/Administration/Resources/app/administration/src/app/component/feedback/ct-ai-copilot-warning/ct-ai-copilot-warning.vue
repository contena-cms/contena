<template>
    <ct-block name="sw_ai_copilot_warning">
        <div class="ct-ai-copilot-warning">
            <mt-icon name="solid-exclamation-triangle" size="14px" />
            {{ warningText }}
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-ai-copilot-warning.scss';

const props = defineProps({
    text: {
        type: String,
        required: false,
        default: '',
    },
});

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const warningText = computed(() => {
    return props.text || t('ct-ai-copilot-warning.text');
});

swDefinePublic({
    warningText,
});

defineExpose({
    warningText,
});
</script>
