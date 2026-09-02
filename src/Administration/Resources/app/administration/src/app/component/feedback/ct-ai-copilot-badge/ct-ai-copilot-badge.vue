<template>
    <ct-block name="ct_ai_copilot_badge">
        <!-- Information: the text should be harcoded and non translatable -->
        <div class="ct-ai-copilot-badge">
            <mt-icon
                v-tooltip="{
                    message: 'AI Copilot',
                    disabled: label,
                    width: 98,
                }"
                name="solid-sparkles"
                size="12px"
            />

            <div v-if="label" class="ct-ai-copilot-badge__label">
                <span class="disambiguation"> AI </span>
                <span> Copilot </span>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-ai-copilot-badge.scss';

defineProps({
    label: {
        type: Boolean,
        required: false,
        default: true,
    },
});

ctDefinePublic({});
</script>
