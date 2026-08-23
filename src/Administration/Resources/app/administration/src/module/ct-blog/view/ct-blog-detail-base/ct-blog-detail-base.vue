<template>
    <ct-block name="sw_blog_detail_base">
        <div class="ct-blog-detail-base">
            <template v-if="isLoading">
                <ct-skeleton variant="detail-bold" />
                <ct-skeleton />
            </template>

            <template v-else>
                <ct-block name="sw_blog_detail_base_basic_info_card">
                    <mt-card
                        class="ct-blog-detail-base__info"
                        position-identifier="ct-blog-detail-base-info"
                        :title="$t('ct-blog.basicForm.cardTitle')"
                    >
                        <ct-blog-basic-form :allow-edit="allowEdit" />
                    </mt-card>
                </ct-block>

                <ct-block name="sw_blog_detail_base_category_card">
                    <mt-card
                        class="ct-blog-detail-base__visibility-structure"
                        position-identifier="ct-blog-detail-base-visibility-structure"
                        :title="$t('ct-blog.categoryForm.cardTitle')"
                    >
                        <ct-blog-category-form :allow-edit="allowEdit" />
                    </mt-card>
                </ct-block>

                <ct-block name="sw_blog_detail_base_media_card">
                    <mt-card
                        class="ct-blog-detail-base__media"
                        position-identifier="ct-blog-detail-base-media"
                        :title="$t('ct-blog.mediaForm.cardTitle')"
                    >
                        <ct-blog-media-form :disabled="!allowEdit" file-accept="image/*" @media-open="onOpenMediaModal" />
                    </mt-card>
                </ct-block>

                <ct-block name="sw_blog_detail_base_media_modal">
                    <ct-media-modal-v2
                        v-if="showMediaModal"
                        :initial-folder-id="mediaDefaultFolderId"
                        :entity-context="blog.getEntityName()"
                        @media-modal-selection-change="onAddMedia"
                        @modal-close="onCloseMediaModal"
                    />
                </ct-block>
            </template>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

import './ct-blog-detail-base.scss';

defineProps({});
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const isLoading = computed(() => Contena.Store.get('swBlogDetail').isLoading);
const allowEdit = computed(() => acl.can('blog.editor'));
const showMediaModal = ref(false);
const mediaDefaultFolderId = ref<string | null>(null);
const blogMediaRepository = computed(() => repositoryFactory.create('blog_media'));
const mediaDefaultFolderRepository = computed(() => repositoryFactory.create('media_default_folder'));
const mediaDefaultFolderCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.addAssociation('folder');
    criteria.addFilter(Contena.Data.Criteria.equals('entity', 'blog'));

    return criteria;
});
const getMediaDefaultFolderId = async (): Promise<string | null> => {
    const defaultFolders = await mediaDefaultFolderRepository.value.search(mediaDefaultFolderCriteria.value, {
        ...Contena.Context.api,
        cacheKey: [
            'media-default-folder',
            'blog',
        ],
    });

    return defaultFolders.first()?.folder?.id ?? null;
};
const onOpenMediaModal = (): void => {
    showMediaModal.value = true;
};
const onCloseMediaModal = (): void => {
    showMediaModal.value = false;
};
const isSpatial = (media: Entity<'blog_media'>): boolean =>
    media.media?.fileExtension === 'glb' || Boolean(media.media?.url?.endsWith('.glb'));
const isExistingMedia = (media: Entity<'media'>): boolean =>
    blog.value.media.some(({ id, mediaId }) => id === media.id || mediaId === media.id);
const setMediaAsCover = (media: Entity<'blog_media'>): void => {
    media.position = 0;
    blog.value.coverId = media.id;
};
const addMedia = (media: Entity<'media'>): Promise<void> => {
    if (isExistingMedia(media)) {
        // A duplicate media association is represented by a rejected promise for the caller.
        // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors
        return Promise.reject(media);
    }

    const newMedia = blogMediaRepository.value.create(Contena.Context.api);
    newMedia.mediaId = media.id;
    newMedia.media = {
        id: media.id,
        url: media.url,
    } as Entity<'media'>;
    newMedia.position = blog.value.media.length;

    if (Contena.Utils.types.isEmpty(blog.value.media) && !isSpatial(newMedia)) {
        setMediaAsCover(newMedia);
    }

    blog.value.media.add(newMedia);

    return Promise.resolve();
};
const onAddMedia = (media: Entity<'media'>[] | null): void => {
    if (Contena.Utils.types.isEmpty(media)) {
        return;
    }

    media?.forEach((item) => {
        void addMedia(item).catch(({ fileName }: Entity<'media'>) => {
            createNotificationError({
                message: t('ct-blog.mediaForm.errorMediaItemDuplicated', { fileName }),
            });
        });
    });
    onCloseMediaModal();
};

void getMediaDefaultFolderId().then((folderId) => {
    mediaDefaultFolderId.value = folderId;
});

swDefinePublic({
    blog,
    isLoading,
    allowEdit,
    showMediaModal,
    mediaDefaultFolderId,
    blogMediaRepository,
    mediaDefaultFolderRepository,
    mediaDefaultFolderCriteria,
    getMediaDefaultFolderId,
    onOpenMediaModal,
    onCloseMediaModal,
    onAddMedia,
    addMedia,
    isSpatial,
    isExistingMedia,
    setMediaAsCover,
});

defineExpose({
    blog,
    isLoading,
    allowEdit,
    showMediaModal,
    mediaDefaultFolderId,
    blogMediaRepository,
    mediaDefaultFolderRepository,
    mediaDefaultFolderCriteria,
    getMediaDefaultFolderId,
    onOpenMediaModal,
    onCloseMediaModal,
    onAddMedia,
    addMedia,
    isSpatial,
    isExistingMedia,
    setMediaAsCover,
});
</script>
