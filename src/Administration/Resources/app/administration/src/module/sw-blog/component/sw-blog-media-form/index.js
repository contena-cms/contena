/*
 * @sw-package inventory
 */

import template from './sw-blog-media-form.html.twig';
import './sw-blog-media-form.scss';

const { Mixin } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'systemConfigApiService'],

    emits: ['media-open'],

    mixins: [Mixin.getByName('notification')],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false,
        },

        fileAccept: {
            type: String,
            required: false,
            default: '*/*',
        },
    },

    data() {
        return {
            showCoverLabel: true,
            isMediaLoading: false,
            columnCount: 5,
            columnWidth: 90,
            globalIsArReady: false,
        };
    },

    computed: {
        blog() {
            const state = Contena.Store.get('swProductDetail');

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.blog;
        },

        galleryItems() {
            return this.blogMedia.slice();
        },

        mediaItems() {
            const mediaItems = this.galleryItems.slice();
            const placeholderCount = this.getPlaceholderCount(this.columnCount);

            if (placeholderCount === 0) {
                return mediaItems;
            }

            for (let i = 0; i < placeholderCount; i += 1) {
                mediaItems.push(this.createPlaceholderMedia(mediaItems));
            }
            return mediaItems;
        },

        cover() {
            if (this.blogMedia.length === 0) {
                return null;
            }
            const coverId = this.blog.cover ? this.blog.cover.mediaId : this.blog.coverId;
            return this.blogMedia.find((media) => media.id === coverId);
        },

        isStoreLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        isLoading() {
            return this.isMediaLoading || this.isStoreLoading;
        },

        blogMediaRepository() {
            return this.repositoryFactory.create('blog_media');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        blogMedia() {
            if (!this.blog?.media) {
                return [];
            }
            return this.blog.media;
        },

        blogMediaStore() {
            return this.blog.getAssociation('media');
        },

        gridAutoRows() {
            return `grid-auto-rows: ${this.columnWidth}`;
        },

        currentCoverID() {
            const coverMediaItem = this.blogMedia.find((coverMedium) => coverMedium.media.id === this.blog.coverId);

            return coverMediaItem.id;
        },
    },

    created() {
        this.onCreated();
    },

    methods: {
        onCreated() {
            this.systemConfigApiService
                .getValues('core.media')
                .then((response) => {
                    this.globalIsArReady = response['core.media.defaultEnableAugmentedReality'];
                })
                .catch((error) => {
                    throw error;
                });
        },

        onOpenMedia() {
            this.$emit('media-open');
        },

        updateColumnCount() {
            this.$nextTick(() => {
                if (this.isLoading) {
                    return false;
                }

                const cssColumns = window
                    .getComputedStyle(this.$refs.grid, null)
                    .getPropertyValue('grid-template-columns')
                    .split(' ');
                this.columnCount = cssColumns.length;
                this.columnWidth = cssColumns[0];

                return true;
            });
        },

        getPlaceholderCount(columnCount) {
            if (this.galleryItems.length + 3 < columnCount * 2) {
                columnCount *= 2;
            }

            let placeholderCount = columnCount;

            if (this.galleryItems.length !== 0) {
                placeholderCount = columnCount - (this.galleryItems.length % columnCount);
                if (placeholderCount === columnCount) {
                    return 0;
                }
            }

            return placeholderCount;
        },

        createPlaceholderMedia(mediaItems) {
            return {
                isPlaceholder: true,
                isCover: mediaItems.length === 0,
                media: {
                    isPlaceholder: true,
                    name: '',
                },
                mediaId: mediaItems.length.toString(),
            };
        },

        buildProductMedia(mediaId) {
            this.isLoading = true;

            const blogMedia = this.blogMediaStore.create();
            blogMedia.mediaId = mediaId;

            if (this.blogMedia.length === 0) {
                blogMedia.position = 0;
                this.blog.cover = blogMedia;
                this.blog.coverId = blogMedia.id;
            } else {
                blogMedia.position = this.blogMedia.length + 1;
            }
            this.isLoading = false;

            return blogMedia;
        },

        async successfulUpload({ targetId }) {
            const existingMedia = this.blog.media.find((blogMedia) => blogMedia.mediaId === targetId);

            // on replace
            if (existingMedia) {
                const mediaItem = await this.mediaRepository.get(targetId);

                const blogMedia = this.createMediaAssociation(targetId);
                blogMedia.media = mediaItem;

                const existingMediaWasCover = this.blog.cover?.id === existingMedia.id;

                // replace the media item
                this.blog.media.remove(existingMedia.id);
                this.blog.media.add(blogMedia);

                if (existingMediaWasCover) {
                    this.blog.coverId = blogMedia.id;
                    this.blog.cover = blogMedia;
                }

                return;
            }

            const blogMedia = this.createMediaAssociation(targetId);
            this.blog.media.add(blogMedia);
        },

        createMediaAssociation(targetId) {
            const blogMedia = this.blogMediaRepository.create();

            blogMedia.blogId = this.blog.id;
            blogMedia.mediaId = targetId;

            if (this.blog.media.length <= 0) {
                blogMedia.position = 0;
                this.blog.coverId = blogMedia.id;
            } else {
                blogMedia.position = this.blog.media.length;
            }
            return blogMedia;
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.blog.media.find((blogMedia) => {
                return blogMedia.mediaId === uploadTask.targetId;
            });
            if (toRemove) {
                if (this.blog.coverId === toRemove.id) {
                    this.blog.coverId = null;
                }
                this.blog.media.remove(toRemove.id);
            }
            this.blog.isLoading = false;
        },

        removeCover() {
            this.blog.cover = null;
            this.blog.coverId = null;
        },

        isCover(blogMedia) {
            const coverId = this.blog.cover ? this.blog.cover.id : this.blog.coverId;

            if (this.blogMedia.length === 0 || blogMedia.isPlaceholder) {
                return false;
            }

            return blogMedia.id === coverId;
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isSpatial(blogMedia) {
            // we need to check the media url since media.fileExtension is set directly after upload
            return blogMedia.media?.fileExtension === 'glb' || !!blogMedia.media?.url?.endsWith('.glb');
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isArReady(blogMedia) {
            return blogMedia.media?.config?.spatial?.arReady ?? this.globalIsArReady;
        },

        removeFile(blogMedia) {
            // remove cover id if mediaId matches
            if (this.blog.coverId === blogMedia.id) {
                this.blog.cover = null;
                this.blog.coverId = null;
            }

            if (this.blog.coverId === null && this.blog.media.length > 0) {
                this.blog.coverId = this.blog.media.first().id;
            }

            this.blog.media.remove(blogMedia.id);
        },

        markMediaAsCover(blogMedia) {
            this.blog.cover = blogMedia;
            this.blog.coverId = blogMedia.id;

            this.moveGalleryItem(blogMedia, 0);
        },

        onDropMedia(dragData) {
            if (this.blog.media.find((blogMedia) => blogMedia.mediaId === dragData.id)) {
                return;
            }

            const blogMedia = this.createMediaAssociation(dragData.mediaItem.id);
            if (this.blog.media.length === 0) {
                // set media item as cover
                blogMedia.position = 0;
                this.blog.cover = blogMedia;
                this.blog.coverId = blogMedia.id;
            }

            this.blog.media.add(blogMedia);
        },

        onMediaItemDragSort(dragData, dropData, validDrop) {
            if (
                validDrop !== true ||
                (dragData.id === this.blog.coverId && dragData.position === 0) ||
                (dropData.id === this.blog.coverId && dropData.position === 0)
            ) {
                return;
            }

            this.moveGalleryItem(dragData, dropData.position);
        },

        moveGalleryItem(item, position) {
            this.blog.media.moveItem(item.position, position);
            this.updateMediaItemPositions();
        },

        updateMediaItemPositions() {
            this.blogMedia.forEach((medium, index) => {
                medium.position = index;
            });
        },
    },
};
