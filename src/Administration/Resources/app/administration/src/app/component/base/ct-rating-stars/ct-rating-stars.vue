<template>
    <ct-block name="sw_rating_stars">
        <div v-tooltip="ratingTooltip" class="ct-rating-stars" :style="dynamicWidthStyle">
            <ct-block name="sw_rating_stars_placeholder">
                <div class="ct-rating-stars__placeholder">
                    <ct-block name="sw_rating_stars_placeholder_stars">
                        <mt-icon
                            v-for="currentStar in maxStars"
                            :key="`placeholder${currentStar}`"
                            class="ct-rating-stars__star star-empty"
                            name="solid-star"
                            :size="iconSize.toString()"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_rating_stars_value">
                <div class="ct-rating-stars__value">
                    <ct-block name="sw_rating_stars_value_stars">
                        <ct-block name="sw_rating_stars_value_stars_full">
                            <mt-icon
                                v-for="currentStar in Math.floor(cappedValue)"
                                :key="`full${currentStar}`"
                                class="ct-rating-stars__star star-full"
                                name="solid-star"
                                :size="iconSize.toString()"
                            />
                        </ct-block>

                        <ct-block name="sw_rating_stars_value_stars_partial">
                            <mt-icon
                                v-if="cappedValue % 1 > 0"
                                class="ct-rating-stars__star star-partial"
                                :style="partialStarCutStyle"
                                name="solid-star"
                                :size="iconSize.toString()"
                            />
                        </ct-block>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-rating-stars.scss';

const props = defineProps({
    value: {
        type: Number,
        required: true,
    },

    maxStars: {
        type: Number,
        required: false,
        default: 5,
    },

    iconSize: {
        type: Number,
        required: false,
        default: 16,
    },

    displayFractions: {
        type: Number,
        required: false,
        default: 4,
        validator(value) {
            return value > 0 && value <= 100;
        },
    },
});

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const ratingTooltip = computed(() => {
    return {
        message: t(
            'ct-rating-stars.ratingTooltipText',
            {
                actual: cappedValue.value,
                max: props.maxStars,
            },
            0,
        ),
    };
});
const cappedValue = computed(() => {
    return Math.min(props.value, props.maxStars);
});
const partialStarCutStyle = computed(() => {
    const negatedPartialValue = 1 - (props.value % 1);
    const percentage = (Math.round(negatedPartialValue * props.displayFractions) * 100) / props.displayFractions;

    // Adjusting styles to make the changes more visible
    let stylePercentage = percentage;
    if (percentage >= 25 && percentage < 50) {
        stylePercentage += 10;
    } else if (percentage <= 75 && percentage > 50) {
        stylePercentage -= 10;
    }

    return `clip-path: inset(0 ${stylePercentage}% 0 0)`;
});
const dynamicWidthStyle = computed(() => {
    return `width: ${props.maxStars * props.iconSize + props.maxStars - 1}px;`;
});

swDefinePublic({
    ratingTooltip,
    cappedValue,
    partialStarCutStyle,
    dynamicWidthStyle,
});

defineExpose({
    ratingTooltip,
    cappedValue,
    partialStarCutStyle,
    dynamicWidthStyle,
});
</script>
