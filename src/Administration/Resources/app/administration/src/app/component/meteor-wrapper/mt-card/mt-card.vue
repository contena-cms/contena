<template>
    <ct-block name="mt_card">
        <!-- mt-card is using fragments, therefore v-show doesn't work.
 This "div" is needed to make it work again.  -->
        <div class="mt-card__wrapper">
            <MtCard v-bind="$attrs">
                <template #before-card>
                    <slot name="before-card"></slot>
                </template>

                <template v-for="(index, name) in getFilteredSlots()" #[name]="data">
                    <slot :name="name" v-bind="data"></slot>
                </template>

                <template #after-card>
                    <slot name="after-card"></slot>
                </template>
            </MtCard>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import MtCard from '@contena/meteor-component-library/dist/esm/MtCard';

defineOptions({ inheritAttrs: false });

defineProps({
    positionIdentifier: {
        type: String,
        required: true,
        default: null,
    },
});

import { useSlots } from 'vue';

const slots = useSlots();

const getFilteredSlots = () => {
    let allSlots: {
        [key: string]: unknown;
    } = {};

    allSlots = slots;

    // Create a new object with the slots we want to keep as deleting is not possible because of read only protection
    const filteredSlots = Object.entries(allSlots).reduce(
        (
            acc,
            [
                key,
                value,
            ],
        ) => {
            if (key !== 'before-card' && key !== 'after-card') {
                acc[key] = value;
            }
            return acc;
        },
        {} as { [key: string]: unknown },
    );

    return filteredSlots;
};

swDefinePublic({
    getFilteredSlots,
});

defineExpose({
    getFilteredSlots,
});
</script>
