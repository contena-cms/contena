<template>
    <ct-block name="ct_help_text">
        <span
            v-tooltip="{
                message: text,
                width: width,
                position: tooltipPosition,
                showDelay: showDelay,
                hideDelay: hideDelay,
            }"
            class="ct-help-text"
        >
            <ct-block name="ct_help_text_icon">
                <mt-icon name="solid-question-circle-s" />
            </ct-block>
        </span>
    </ct-block>
</template>

<script setup>
import './ct-help-text.scss';

defineProps({
    text: {
        type: String,
        required: true,
        default: '',
    },
    width: {
        type: Number,
        required: false,
        default: 200,
    },
    tooltipPosition: {
        type: String,
        required: false,
        default: 'top',
        validValues: [
            'top',
            'bottom',
            'left',
            'right',
        ],
        validator(value) {
            return [
                'top',
                'bottom',
                'left',
                'right',
            ].includes(value);
        },
    },
    showDelay: {
        type: Number,
        required: false,
        default: 100,
    },
    hideDelay: {
        type: Number,
        required: false,
        default: 100,
    },
});

ctDefinePublic({});
</script>
