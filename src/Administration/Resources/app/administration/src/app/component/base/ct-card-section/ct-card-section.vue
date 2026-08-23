<template>
    <ct-block name="sw_card_section">
        <div class="ct-card-section" :class="cardSectionClasses">
            <slot></slot>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-card-section.scss';

const props = defineProps({
    divider: {
        type: String,
        required: false,
        default: '',
        validValues: [
            'top',
            'right',
            'bottom',
            'left',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'top',
                'right',
                'bottom',
                'left',
            ].includes(value);
        },
    },
    secondary: {
        type: Boolean,
        required: false,
        default: false,
    },
    slim: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed } from 'vue';

const cardSectionClasses = computed(() => {
    return {
        [`ct-card-section--divider-${props.divider}`]: props.divider,
        'ct-card-section--secondary': props.secondary,
        'ct-card-section--slim': props.slim,
    };
});

swDefinePublic({
    cardSectionClasses,
});

defineExpose({
    cardSectionClasses,
});
</script>
