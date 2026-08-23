<template>
    <ct-block name="sw_skeleton">
        <div class="ct-skeleton" :class="classList">
            <template v-if="variant === 'detail'">
                <mt-skeleton-bar class="ct-skeleton-bar" style="width: 170px" />

                <div class="ct-skeleton__detail-container">
                    <mt-skeleton-bar class="ct-skeleton-bar" style="width: 359px" />
                    <mt-skeleton-bar class="ct-skeleton-bar" style="width: 323px" />
                </div>

                <mt-skeleton-bar class="ct-skeleton-bar" style="width: 443px" />
                <mt-skeleton-bar class="ct-skeleton-bar" style="width: 234px" />
            </template>

            <template v-if="variant === 'detail-bold'">
                <mt-skeleton-bar class="ct-skeleton-bar" style="width: 520px" />

                <div class="ct-skeleton__detail-container">
                    <mt-skeleton-bar class="ct-skeleton-bar" style="width: 359px" />
                    <mt-skeleton-bar class="ct-skeleton-bar" style="width: 323px" />
                </div>

                <mt-skeleton-bar class="ct-skeleton-bar" style="height: 122px" />
            </template>

            <template v-if="variant === 'gallery'">
                <mt-skeleton-bar class="ct-skeleton-bar" />
            </template>

            <template v-if="variant === 'media'">
                <mt-skeleton-bar class="ct-skeleton-bar ct-skeleton__media-preview" />
                <mt-skeleton-bar class="ct-skeleton-bar ct-skeleton__media-subtitle" />
            </template>

            <template v-if="['tree-item', 'tree-item-nested'].includes(variant)">
                <mt-skeleton-bar class="ct-skeleton-bar ct-skeleton__tree-item-checkbox" />
                <mt-skeleton-bar class="ct-skeleton-bar ct-skeleton__tree-item-text" />
            </template>

            <template v-if="variant === 'listing'">
                <mt-skeleton-bar class="ct-skeleton-bar" />
            </template>

            <template v-if="variant === 'extension-list'">
                <mt-skeleton-bar class="ct-skeleton-bar" />
                <mt-skeleton-bar class="ct-skeleton-bar" />
                <mt-skeleton-bar class="ct-skeleton-bar" />
                <mt-skeleton-bar class="ct-skeleton-bar" />
                <mt-skeleton-bar class="ct-skeleton-bar" />
                <mt-skeleton-bar class="ct-skeleton-bar" />
                <mt-skeleton-bar class="ct-skeleton-bar" />
            </template>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-skeleton.scss';

const props = defineProps({
    variant: {
        type: String,
        required: false,
        default: 'detail',
        validator(value: string) {
            const variants = [
                'gallery',
                'detail',
                'detail-bold',
                'category',
                'listing',
                'tree-item',
                'tree-item-nested',
                'media',
                'extension-list',
            ];

            return variants.includes(value);
        },
    },
});

import { computed } from 'vue';

const classList = computed(() => {
    return {
        'ct-skeleton__gallery': props.variant === 'gallery',
        'ct-skeleton__detail': props.variant === 'detail',
        'ct-skeleton__detail-bold': props.variant === 'detail-bold',
        'ct-skeleton__category': props.variant === 'category',
        'ct-skeleton__listing': props.variant === 'listing',
        'ct-skeleton__tree-item': props.variant === 'tree-item',
        'ct-skeleton__tree-item-nested': props.variant === 'tree-item-nested',
        'ct-skeleton__media': props.variant === 'media',
        'ct-skeleton__extension-list': props.variant === 'extension-list',
    };
});

swDefinePublic({
    classList,
});

defineExpose({
    classList,
});
</script>
