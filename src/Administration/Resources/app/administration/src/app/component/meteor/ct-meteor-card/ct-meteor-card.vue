<template>
    <ct-block name="ct_meteor_card">
        <div class="ct-meteor-card" :class="cardClasses">
            <ct-block name="ct_meteor_card_header">
                <div v-if="hasHeader" class="ct-meteor-card__header">
                    <ct-block name="ct_meteor_card_header_grid">
                        <div v-if="!!title || !!$slots.action" class="ct-meteor-card__header-grid">
                            <ct-block name="ct_meteor_card_title">
                                <slot name="title">
                                    <div v-if="title" class="ct-meteor-card__title">
                                        {{ title }}
                                    </div>
                                </slot>
                            </ct-block>

                            <ct-block name="ct_meteor_card_header_action">
                                <div v-if="!!$slots.action" class="ct-meteor-card__header-action">
                                    <ct-block name="ct_meteor_card_header_action_inner">
                                        <slot name="action">
                                            <ct-block name="ct_meteor_card_slot_header_action"></ct-block>
                                        </slot>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="ct_meteor_card_toolbar">
                        <div v-if="!!$slots.toolbar" class="ct-meteor-card__toolbar">
                            <ct-block name="ct_meteor_card_toolbar_inner">
                                <slot name="toolbar">
                                    <ct-block name="ct_meteor_card_slot_toolbar"></ct-block>
                                </slot>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_meteor_card_content">
                <div v-if="hasContent" class="ct-meteor-card__content">
                    <ct-block name="ct_meteor_card_content_wrapper">
                        <div v-if="hasDefaultSlot" class="ct-meteor-card__content-wrapper">
                            <ct-block name="ct_meteor_card_default_inner">
                                <slot>
                                    <ct-block name="ct_meteor_card_slot_default"></ct-block>
                                </slot>
                            </ct-block>
                        </div>
                    </ct-block>

                    <slot name="grid" :title="title">
                        <ct-block name="ct_meteor_card_slot_grid"></ct-block>
                    </slot>

                    <ct-block name="ct_meteor_card_loader">
                        <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                        <mt-loader v-if="isLoading" />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_meteor_card_footer">
                <div v-if="!!$slots.footer" class="ct-meteor-card__footer">
                    <ct-block name="ct_meteor_card_footer_inner">
                        <slot name="footer">
                            <ct-block name="ct_meteor_card_slot_footer"></ct-block>
                        </slot>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-meteor-card.scss';

const props = defineProps({
    title: {
        type: String,
        required: false,
        default: null,
    },
    hero: {
        type: Boolean,
        required: false,
        default: false,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
    large: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed, useSlots } from 'vue';

const slots = useSlots();

const hasToolbar = computed(() => {
    return !!slots.toolbar;
});
const hasContent = computed(() => {
    return !!slots.default || !!slots.grid;
});
const hasDefaultSlot = computed(() => {
    return !!slots.default;
});
const hasHeader = computed(() => {
    return hasToolbar.value || !!props.title || !!slots.action;
});
const isToolbarLastHeaderElement = computed(() => {
    return hasToolbar.value;
});
const cardClasses = computed(() => {
    return {
        'ct-meteor-card--toolbar': hasToolbar.value,
        'ct-meteor-card--hero': !!props.hero,
        'ct-meteor-card--large': props.large,
        'has--header': hasHeader.value && !isToolbarLastHeaderElement.value,
    };
});

ctDefinePublic({
    hasToolbar,
    hasContent,
    hasDefaultSlot,
    hasHeader,
    isToolbarLastHeaderElement,
    cardClasses,
});

defineExpose({
    hasToolbar,
    hasContent,
    hasDefaultSlot,
    hasHeader,
    isToolbarLastHeaderElement,
    cardClasses,
});
</script>
