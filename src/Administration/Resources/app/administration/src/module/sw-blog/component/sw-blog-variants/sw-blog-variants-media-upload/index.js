/*
 * @sw-package inventory
 */

import template from './sw-blog-variants-media-upload.html.twig';
import './sw-blog-variants-media-upload.scss';

const { Mixin, Context } = Contena;
const { isEmpty } = Contena.Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'mediaDefaultFolderService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        source: {
            type: Object,
            required: true,
        },

        parentProduct: {
            type: Object,
            required: true,
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showMediaModal: false,
            mediaDefaultFolderId: null,
            showPreviewModal: false,
            activeItemId: null,
        };
    },

    computed: {
        blogMediaRepository() {
            return this.repositoryFactory.create(this.source.media.entity);
        },

        blog() {
            if (this.isInherited) {
                return this.parentProduct;
            }

            return this.source;
        },

        mediaSource() {
            if (!this.blog) {
                return [];
            }
            const media = [...this.blog.media];

            return media.sort((a, b) => a.position - b.position);
        },

        cover() {
            if (!this.blog) {
                return null;
            }

            return this.blog.media.find((media) => media.id === this.blog.coverId);
        },

        coverImageSource() {
            if (this.cover) {
                return this.cover.media ?? this.cover.mediaId;
            }

            return this.blog?.cover?.mediaId ?? this.blog?.cover?.media;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getMediaDefaultFolderId()
                .then((id) => {
                    this.mediaDefaultFolderId = id;
                    this.defaultFolderId = id;
                    this.updateMediaItemPositions();
                })
                .catch(() => {
                    this.mediaDefaultFolderId = null;
                    this.defaultFolderId = null;
                });
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderService.getDefaultFolderId('blog');
        },

        isCover(blogMedia) {
            const coverId = this.blog.cover ? this.blog.cover.id : this.blog.coverId;

            if (this.blog.media.length === 0) {
                return false;
            }

            return blogMedia.id === coverId;
        },

        markMediaAsCover(blogMedia) {
            this.blog.cover = blogMedia;
            this.blog.coverId = blogMedia.id;
        },

        removeMedia(blogMedia) {
            if (this.blog.coverId === blogMedia.id) {
                this.blog.cover = null;
                this.blog.coverId = null;
            }

            if (this.blog.coverId === null && this.blog.media.length > 0) {
                this.blog.coverId = this.blog.media.first().id;
            }

            this.blog.media.remove(blogMedia.id);
        },

        onAddMedia(media) {
            if (isEmpty(media)) {
                return;
            }

            media.forEach((item) => {
                this.addMedia(item).catch(({ fileName }) => {
                    this.createNotificationError({
                        message: this.$t('sw-blog.mediaForm.errorMediaItemDuplicated', { fileName }, 0),
                    });
                });
            });
            this.updateMediaItemPositions();
        },

        addMedia(media) {
            if (this.isExistingMedia(media)) {
                return Promise.reject(media);
            }

            const newMedia = this.blogMediaRepository.create(Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = { url: media.url, id: media.id };

            if (isEmpty(this.source.media)) {
                this.source.cover = newMedia;
                this.source.coverId = newMedia.id;
            }

            this.source.media.add(newMedia);

            return Promise.resolve();
        },

        isExistingMedia(media) {
            return this.source.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },

        onUploadMediaSuccessful({ targetId }) {
            if (this.isReplacedMedia(targetId)) {
                return;
            }

            const newMedia = this.blogMediaRepository.create(Context.api);
            newMedia.blogId = this.source.id;
            newMedia.mediaId = targetId;

            if (isEmpty(this.source.media)) {
                newMedia.position = 0;
                this.source.coverId = newMedia.id;
            } else {
                newMedia.position = this.source.media.length;
            }

            this.source.media.add(newMedia);
            this.updateMediaItemPositions();
        },

        isReplacedMedia(targetId) {
            return this.source.media.find((sourceMedia) => {
                return sourceMedia.mediaId === targetId;
            });
        },

        onUploadMediaFailed({ targetId }) {
            const newMedia = this.source.media.find((sourceMedia) => {
                return sourceMedia.mediaId === targetId;
            });

            if (newMedia) {
                if (this.source.coverId === newMedia.id) {
                    this.source.coverId = null;
                }
                this.source.media.remove(newMedia.id);
            }

            this.source.isLoading = false;
        },

        previewMedia(item) {
            this.activeItemId = item.id;
            this.showPreviewModal = true;
        },

        onClosePreviewModal() {
            this.activeItemId = null;
            this.showPreviewModal = false;
        },

        updateMediaItemPositions() {
            this.source.media.forEach((medium, index) => {
                medium.position = index;
            });
        },
    },
};
