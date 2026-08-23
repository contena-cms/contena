<template>
    <ct-block name="sw_blog_seo_form">
        <div class="ct-blog-seo-form">
            <ct-block name="sw_blog_seo_form_meta_title">
                <mt-text-field
                    v-model="blog.metaTitle"
                    :label="$t('ct-blog.seoForm.labelMetaTitle')"
                    :help-text="$t('ct-blog.seoForm.helpTextMetaTitle')"
                    :placeholder="$t('ct-blog.seoForm.placeholderMetaTitle')"
                    :error="blogMetaTitleError"
                    :disabled="!allowEdit || undefined"
                />
            </ct-block>

            <ct-block name="sw_blog_seo_form_meta_description">
                <mt-textarea
                    v-model="blog.metaDescription"
                    :label="$t('ct-blog.seoForm.labelMetaDescription')"
                    :help-text="$t('ct-blog.seoForm.helpTextMetaDescription')"
                    :placeholder="$t('ct-blog.seoForm.placeholderMetaDescription')"
                    :error="blogMetaDescriptionError"
                    :disabled="!allowEdit || undefined"
                />
            </ct-block>

            <ct-block name="sw_blog_seo_form_keywords">
                <mt-text-field
                    v-model="blog.keywords"
                    :label="$t('ct-blog.seoForm.labelKeywords')"
                    :help-text="$t('ct-blog.seoForm.helpTextKeywords')"
                    :placeholder="$t('ct-blog.seoForm.placeholderKeywords')"
                    :error="blogKeywordsError"
                    :disabled="!allowEdit || undefined"
                />
            </ct-block>

            <ct-block name="sw_blog_seo_form_open_graph">
                <div class="ct-blog-seo-form__open-graph">
                    <h3>{{ $t('ct-blog.seoForm.titleOpenGraph') }}</h3>

                    <mt-text-field
                        v-model="blog.ogTitle"
                        :label="$t('ct-blog.seoForm.labelOpenGraphTitle')"
                        :help-text="$t('ct-blog.seoForm.helpTextOpenGraphTitle')"
                        :placeholder="$t('ct-blog.seoForm.placeholderOpenGraphTitle')"
                        :error="blogOgTitleError"
                        :disabled="!allowEdit || undefined"
                    />

                    <mt-textarea
                        v-model="blog.ogDescription"
                        :label="$t('ct-blog.seoForm.labelOpenGraphDescription')"
                        :help-text="$t('ct-blog.seoForm.helpTextOpenGraphDescription')"
                        :placeholder="$t('ct-blog.seoForm.placeholderOpenGraphDescription')"
                        :error="blogOgDescriptionError"
                        :disabled="!allowEdit || undefined"
                    />

                    <ct-media-upload-v2
                        variant="regular"
                        :upload-tag="openGraphMediaUploadTag"
                        :source="openGraphMediaItem"
                        :allow-multi-select="false"
                        :disabled="!allowEdit || undefined"
                        :caption="$t('ct-blog.seoForm.labelOpenGraphImage')"
                        file-accept="image/*"
                        @media-upload-sidebar-open="onOpenOgMediaModal"
                        @media-upload-remove-image="onRemoveOgMedia"
                    />

                    <ct-upload-listener
                        :upload-tag="openGraphMediaUploadTag"
                        auto-upload
                        @media-upload-finish="onOgMediaUploadFinish"
                    />

                    <ct-media-modal-v2
                        v-if="showOgMediaModal"
                        variant="regular"
                        :caption="$t('ct-blog.seoForm.labelOpenGraphImage')"
                        :allow-multi-select="false"
                        @media-modal-selection-change="onOgMediaSelectionChange"
                        @modal-close="onCloseOgMediaModal"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref, watch } from 'vue';

defineProps({
    allowEdit: {
        type: Boolean,
        default: true,
    },
});

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const mediaRepository = computed(() => repositoryFactory.create('media'));
const currentOpenGraphMediaId = computed(() => blog.value.openGraphMediaId ?? null);
const showOgMediaModal = ref(false);
const openGraphMediaItem = ref<Entity<'media'> | null>(null);
const openGraphMediaUploadTag = `ct-blog-seo-form-og-image-${Contena.Utils.createId().substring(0, 8)}`;
const getApiError = (property: string): unknown => Contena.Store.get('error').getApiError(blog.value, property);
const blogKeywordsError = computed(() => getApiError('keywords'));
const blogMetaDescriptionError = computed(() => getApiError('metaDescription'));
const blogMetaTitleError = computed(() => getApiError('metaTitle'));
const blogOgTitleError = computed(() => getApiError('ogTitle'));
const blogOgDescriptionError = computed(() => getApiError('ogDescription'));
const onOpenOgMediaModal = (): void => {
    showOgMediaModal.value = true;
};
const onCloseOgMediaModal = (): void => {
    showOgMediaModal.value = false;
};
const onRemoveOgMedia = (): void => {
    openGraphMediaItem.value = null;
    blog.value.openGraphMediaId = null;
    blog.value.openGraphMedia = null;
};
const onOgMediaUploadFinish = async ({ targetId }: { targetId: string }): Promise<void> => {
    blog.value.openGraphMediaId = targetId;
    openGraphMediaItem.value = await mediaRepository.value.get(targetId, Contena.Context.api);
    blog.value.openGraphMedia = openGraphMediaItem.value;
};
const onOgMediaSelectionChange = (selection: Entity<'media'>[]): void => {
    if (selection.length !== 1) {
        onRemoveOgMedia();
        return;
    }

    [openGraphMediaItem.value] = selection;
    blog.value.openGraphMediaId = openGraphMediaItem.value.id;
    blog.value.openGraphMedia = openGraphMediaItem.value;
    showOgMediaModal.value = false;
};

watch(
    currentOpenGraphMediaId,
    async (mediaId) => {
        if (!mediaId) {
            openGraphMediaItem.value = null;
            return;
        }

        if (blog.value.openGraphMedia?.id === mediaId) {
            openGraphMediaItem.value = blog.value.openGraphMedia;
            return;
        }

        const media = await mediaRepository.value.get(mediaId, Contena.Context.api);

        if (currentOpenGraphMediaId.value === mediaId) {
            openGraphMediaItem.value = media;
        }
    },
    { immediate: true },
);

swDefinePublic({
    blog,
    mediaRepository,
    currentOpenGraphMediaId,
    showOgMediaModal,
    openGraphMediaItem,
    openGraphMediaUploadTag,
    blogKeywordsError,
    blogMetaDescriptionError,
    blogMetaTitleError,
    blogOgTitleError,
    blogOgDescriptionError,
    onOpenOgMediaModal,
    onCloseOgMediaModal,
    onRemoveOgMedia,
    onOgMediaUploadFinish,
    onOgMediaSelectionChange,
});

defineExpose({
    blog,
    mediaRepository,
    currentOpenGraphMediaId,
    showOgMediaModal,
    openGraphMediaItem,
    openGraphMediaUploadTag,
    blogKeywordsError,
    blogMetaDescriptionError,
    blogMetaTitleError,
    blogOgTitleError,
    blogOgDescriptionError,
    onOpenOgMediaModal,
    onCloseOgMediaModal,
    onRemoveOgMedia,
    onOgMediaUploadFinish,
    onOgMediaSelectionChange,
});
</script>
