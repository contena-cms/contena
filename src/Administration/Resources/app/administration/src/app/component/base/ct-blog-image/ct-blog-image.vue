<template>
    <ct-block name="sw_blog_image">
        <div class="ct-blog-image" :class="blogImageClasses">
            <template v-if="!isPlaceholder">
                <ct-block name="sw_blog_image_preview">
                    <ct-media-preview-v2 class="ct-blog-image__image" :source="mediaId" :hide-tooltip="false" />

                    <ct-label
                        v-if="showCoverLabel && isCover"
                        class="ct-blog-image__cover-label"
                        variant="primary"
                        size="medium"
                        appearance="pill"
                    >
                        {{ $t('ct-blog.mediaForm.coverSubline') }}
                    </ct-label>

                    <ct-block name="sw_blog_image_preview_spatial_labels">
                        <ct-label
                            v-if="isSpatial"
                            class="ct-blog-image__spatial-label"
                            variant="neutral-reversed"
                            size="medium"
                            appearance="pill"
                        >
                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                            <label v-if="isArReady" class="ct-label__ar-ready">
                                {{ $t('ct-blog.mediaForm.arSubline') }}
                            </label>
                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                            <label v-else class="ct-label__spatial">
                                {{ $t('ct-blog.mediaForm.spatialSubline') }}
                            </label>
                        </ct-label>
                    </ct-block>
                </ct-block>
                <ct-context-button class="ct-blog-image__context-button">
                    <ct-block name="sw_blog_image_context">
                        <ct-block name="sw_blog_image_context_cover_action">
                            <ct-context-menu-item
                                v-if="showCoverLabel && !isCover"
                                class="ct-blog-image__button-cover"
                                @click="$emit('ct-blog-image-cover')"
                            >
                                {{ $t('global.ct-blog-image.context.buttonAsCover') }}
                            </ct-context-menu-item>
                        </ct-block>

                        <ct-block name="sw_blog_image_context_delete_action">
                            <ct-context-menu-item
                                variant="danger"
                                class="ct-blog-image__button-delete"
                                @click="$emit('ct-blog-image-delete')"
                            >
                                {{ $t('global.default.remove') }}
                            </ct-context-menu-item>
                        </ct-block>
                    </ct-block>
                </ct-context-button>
            </template>
            <div v-else class="is--invalid-drag">
                <ct-block name="sw_blog_image_placeholder">
                    <mt-icon class="ct-blog-image__placeholder-icon" :name="'regular-image'" size="16px" />
                </ct-block>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import './ct-blog-image.scss';

const props = defineProps({
    mediaId: {
        type: String,
        required: true,
    },

    isSpatial: {
        type: Boolean,
        required: false,
        default: false,
    },

    isArReady: {
        type: Boolean,
        required: false,
        default: false,
    },

    isCover: {
        type: Boolean,
        required: false,
        default: false,
    },

    isPlaceholder: {
        type: Boolean,
        required: false,
        default: false,
    },

    showCoverLabel: {
        type: Boolean,
        required: false,
        default: true,
    },
});
defineEmits<{
    'ct-blog-image-cover': [];
    'ct-blog-image-delete': [];
}>();

const blogImageClasses = computed(() => {
    return {
        'is--placeholder': props.isPlaceholder,
        'is--cover': props.isCover && props.showCoverLabel,
        'is--spatial': props.isSpatial,
    };
});

swDefinePublic({
    blogImageClasses,
});

defineExpose({
    blogImageClasses,
});
</script>
