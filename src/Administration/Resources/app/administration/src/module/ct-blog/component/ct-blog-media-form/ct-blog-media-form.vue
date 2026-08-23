<template>
    <ct-block name="sw_blog_media_form">
        <div class="ct-blog-media-form" :class="{ 'is--disabled': disabled }">
            <ct-block name="sw_blog_media_form_upload">
                <ct-upload-listener
                    v-if="!isLoading"
                    :upload-tag="blog.id"
                    auto-upload
                    @media-upload-finish="successfulUpload"
                    @media-upload-fail="onUploadFailed"
                />

                <ct-media-upload-v2
                    v-if="!isLoading && allowEdit"
                    variant="regular"
                    default-folder="blog"
                    :upload-tag="blog.id"
                    :file-accept="fileAccept"
                    @media-upload-sidebar-open="onOpenMedia"
                />
            </ct-block>

            <ct-block name="sw_blog_media_form_grid">
                <div class="ct-blog-media-form__previews">
                    <div class="ct-blog-media-form__cover-container ct-blog-media-form__column">
                        <ct-block name="sw_blog_media_form_cover_preview">
                            <div v-if="cover" class="ct-blog-media-form__preview-cover">
                                <div class="preview-cover">
                                    <ct-media-preview-v2 class="ct-blog-media-form__cover-image" :source="cover.mediaId" />
                                    <span>{{ $t('ct-blog.mediaForm.coverSubline') }}</span>
                                </div>
                            </div>
                            <div v-else class="ct-blog-media-form__cover-image is--placeholder">
                                {{ $t('ct-blog.mediaForm.coverSubline') }}
                            </div>
                        </ct-block>
                    </div>

                    <div v-if="!isLoading" class="ct-blog-media-form__grid ct-blog-media-form__column">
                        <ct-block name="sw_blog_media_form_grid_items">
                            <ct-blog-image
                                v-for="mediaItem in mediaItems"
                                :key="mediaItem.id"
                                v-draggable="{
                                    dragGroup: 'blog-media',
                                    data: mediaItem,
                                    onDragEnter: onMediaItemDragSort,
                                }"
                                v-droppable="{ dragGroup: 'blog-media', data: mediaItem }"
                                :is-cover="isCover(mediaItem)"
                                :is-spatial="isSpatial(mediaItem)"
                                :is-ar-ready="isArReady(mediaItem)"
                                :is-placeholder="mediaItem.isPlaceholder"
                                :media-id="mediaItem.mediaId"
                                :show-cover-label="showCoverLabel"
                                @ct-blog-image-delete="removeFile(mediaItem)"
                                @ct-blog-image-cover="markMediaAsCover(mediaItem)"
                            />
                        </ct-block>
                    </div>
                    <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                    <mt-loader v-else />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type SystemConfigApiService from 'src/core/service/api/system-config.api.service';
import { computed, inject, ref } from 'vue';

import './ct-blog-media-form.scss';

type BlogMedia = Entity<'blog_media'> & { isPlaceholder?: boolean };

const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    fileAccept: {
        type: String,
        default: '*/*',
    },
    placeholderColumns: {
        type: Number,
        default: 5,
    },
    mediaItemsOverride: {
        type: Array as PropType<BlogMedia[] | null>,
        default: null,
    },
});
const emit = defineEmits<{ 'media-open': [] }>();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const systemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService')!;
const showCoverLabel = ref(true);
const isMediaLoading = ref(false);
const globalIsArReady = ref(false);
const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const allowEdit = computed(() => acl.can('blog.editor') && !props.disabled);
const isLoading = computed(() => isMediaLoading.value || Contena.Store.get('swBlogDetail').isLoading);
const blogMediaRepository = computed(() => repositoryFactory.create('blog_media'));
const mediaRepository = computed(() => repositoryFactory.create('media'));
const blogMedia = computed(() => blog.value.media);
const createPlaceholderMedia = (position: number): BlogMedia =>
    ({
        id: `placeholder-${position}`,
        mediaId: `placeholder-${position}`,
        isPlaceholder: true,
    }) as BlogMedia;
const mediaItems = computed<BlogMedia[]>(() => {
    if (props.mediaItemsOverride) {
        return props.mediaItemsOverride;
    }

    const items = [...blogMedia.value] as BlogMedia[];
    const remainder = items.length % props.placeholderColumns;
    const placeholderCount =
        items.length === 0 ? props.placeholderColumns : remainder === 0 ? 0 : props.placeholderColumns - remainder;

    for (let index = 0; index < placeholderCount; index += 1) {
        items.push(createPlaceholderMedia(items.length + index));
    }

    return items;
});
const cover = computed(() => blogMedia.value.find((media) => media.id === blog.value.coverId) ?? null);
const onOpenMedia = (): void => {
    emit('media-open');
};
const createMediaAssociation = (mediaId: string): Entity<'blog_media'> => {
    const association = blogMediaRepository.value.create(Contena.Context.api);
    association.blogId = blog.value.id;
    association.blogVersionId = blog.value.versionId;
    association.mediaId = mediaId;

    if (blogMedia.value.length === 0) {
        association.position = 0;
        blog.value.coverId = association.id;
    } else {
        association.position = blogMedia.value.length;
    }

    return association;
};
const successfulUpload = async ({ targetId }: { targetId: string }): Promise<void> => {
    isMediaLoading.value = true;

    try {
        const existingMedia = blogMedia.value.find((item) => item.mediaId === targetId);

        if (existingMedia) {
            const media = await mediaRepository.value.get(targetId, Contena.Context.api);
            const association = createMediaAssociation(targetId);
            association.media = media;
            const wasCover = blog.value.coverId === existingMedia.id;
            blogMedia.value.remove(existingMedia.id);
            blogMedia.value.add(association);

            if (wasCover) {
                blog.value.coverId = association.id;
                blog.value.cover = association;
            }
            return;
        }

        const association = createMediaAssociation(targetId);
        blogMedia.value.add(association);
    } finally {
        isMediaLoading.value = false;
    }
};
const onUploadFailed = ({ targetId }: { targetId: string }): void => {
    const failedMedia = blogMedia.value.find((media) => media.mediaId === targetId);

    if (failedMedia) {
        if (blog.value.coverId === failedMedia.id) {
            blog.value.coverId = null;
        }
        blogMedia.value.remove(failedMedia.id);
    }
};
const removeCover = (): void => {
    blog.value.cover = null;
    blog.value.coverId = null;
};
const isCover = (media: BlogMedia): boolean => {
    const coverId = blog.value.cover ? blog.value.cover.id : blog.value.coverId;

    if (blogMedia.value.length === 0 || media.isPlaceholder) {
        return false;
    }

    return media.id === coverId;
};
const isSpatial = (media: BlogMedia): boolean =>
    media.media?.fileExtension === 'glb' || Boolean(media.media?.url?.endsWith('.glb'));
const isArReady = (media: BlogMedia): boolean => media.media?.config?.spatial?.arReady ?? globalIsArReady.value;
const removeFile = (media: BlogMedia): void => {
    if (media.isPlaceholder) {
        return;
    }

    if (blog.value.coverId === media.id) {
        removeCover();
    }

    if (blog.value.coverId === null && blogMedia.value.length > 0) {
        blog.value.coverId = blogMedia.value.first()?.id ?? null;
    }

    blogMedia.value.remove(media.id);
};
const markMediaAsCover = (media: BlogMedia): void => {
    if (media.isPlaceholder) {
        return;
    }

    blog.value.coverId = media.id;
    blog.value.cover = media;
    blogMedia.value.moveItem(media.position ?? 0, 0);
    updateMediaItemPositions();
};
const onDropMedia = (dragData: { id: string; mediaItem: Entity<'media'> }): void => {
    if (blogMedia.value.some((media) => media.mediaId === dragData.id)) {
        return;
    }

    const media = createMediaAssociation(dragData.mediaItem.id);
    if (blogMedia.value.length === 0) {
        media.position = 0;
        blog.value.cover = media;
        blog.value.coverId = media.id;
    }

    blogMedia.value.add(media);
};
const onMediaItemDragSort = (dragData: BlogMedia, dropData: BlogMedia, validDrop: boolean): void => {
    if (
        !validDrop ||
        dragData.isPlaceholder ||
        dropData.isPlaceholder ||
        (dragData.id === blog.value.coverId && dragData.position === 0) ||
        (dropData.id === blog.value.coverId && dropData.position === 0)
    ) {
        return;
    }

    blogMedia.value.moveItem(dragData.position ?? 0, dropData.position ?? 0);
    updateMediaItemPositions();
};
const updateMediaItemPositions = (): void => {
    blogMedia.value.forEach((media, index) => {
        media.position = index;
    });
};

void systemConfigApiService.getValues('core.media').then((config) => {
    globalIsArReady.value = Boolean(config['core.media.defaultEnableAugmentedReality']);
});

swDefinePublic({
    blog,
    allowEdit,
    showCoverLabel,
    isMediaLoading,
    globalIsArReady,
    isLoading,
    blogMediaRepository,
    mediaRepository,
    blogMedia,
    mediaItems,
    cover,
    onOpenMedia,
    createPlaceholderMedia,
    createMediaAssociation,
    successfulUpload,
    onUploadFailed,
    removeCover,
    isCover,
    isSpatial,
    isArReady,
    removeFile,
    markMediaAsCover,
    onDropMedia,
    onMediaItemDragSort,
    updateMediaItemPositions,
});

defineExpose({
    blog,
    allowEdit,
    showCoverLabel,
    isMediaLoading,
    globalIsArReady,
    isLoading,
    blogMediaRepository,
    mediaRepository,
    blogMedia,
    mediaItems,
    cover,
    onOpenMedia,
    createPlaceholderMedia,
    createMediaAssociation,
    successfulUpload,
    onUploadFailed,
    removeCover,
    isCover,
    isSpatial,
    isArReady,
    removeFile,
    markMediaAsCover,
    onDropMedia,
    onMediaItemDragSort,
    updateMediaItemPositions,
});
</script>
