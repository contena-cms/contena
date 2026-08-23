<template>
    <ct-block name="sw_card_view">
        <div class="ct-card-view">
            <ct-block name="sw_card_view_content">
                <div class="ct-card-view__content">
                    <ct-error-summary v-if="showErrorSummary" />

                    <slot>
                        <ct-block name="sw_card_view_slot_default"></ct-block>
                    </slot>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-card-view.scss';

defineProps({
    showErrorSummary: {
        type: Boolean,
        require: false,
        default: true,
    },
});

swDefinePublic({});
</script>
