/**
 * @sw-package inventory
 */

import template from './sw-blog-download-form.html.twig';
import './sw-blog-download-form.scss';

const { Mixin } = Contena;
const { format } = Contena.Utils;

/**
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'configService',
        'mediaService',
    ],

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
    },

    data() {
        return {
            isMediaLoading: false,
            fileAcceptedExtensions: [],
            fileAcceptedMimeTypesByExtension: {},
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

        isStoreLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        isLoading() {
            return this.isMediaLoading || this.isStoreLoading;
        },

        blogDownloadRepository() {
            return this.repositoryFactory.create('blog_download');
        },

        blogDownloads() {
            if (!this.blog) {
                return [];
            }
            return this.blog.downloads;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        error() {
            return Contena.Store.get('error').getApiError(this.blog, 'downloads');
        },

        hasError() {
            return !!this.error;
        },

        swFieldClasses() {
            return {
                'has--error': this.hasError,
            };
        },

        fileAccept() {
            return this.fileAcceptedExtensions.join(', ');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.configService.getConfig().then((result) => {
                this.fileAcceptedExtensions = result.settings.private_allowed_extensions ?? [];
                this.fileAcceptedMimeTypesByExtension = result.settings.private_allowed_mime_types_by_extension ?? {};
            });
        },

        onOpenMedia() {
            this.$emit('media-open');
        },

        getFileSize(download) {
            return format.fileSize(download.media.fileSize);
        },

        getFileName(download) {
            if (download.media.fileExtension) {
                return `${download.media.fileName}.${download.media.fileExtension}`;
            }

            return download.media.fileName;
        },

        createdAt(download) {
            const date = download.media.uploadedAt || download.media.createdAt;
            return format.date(date, {
                month: 'numeric',
            });
        },

        onRemoveDownload(download) {
            this.blog.downloads.remove(download.id);
        },

        async successfulUpload({ targetId }) {
            // on replace
            if (this.blog.downloads.find((blogDownload) => blogDownload.mediaId === targetId)) {
                return;
            }

            const blogDownload = this.createDownloadAssociation(targetId);
            blogDownload.media = await this.mediaRepository.get(targetId);

            this.blog.downloads.add(blogDownload);
            Contena.Store.get('swProductDetail').isDownloadExternallyManaged = false;

            if (this.error) {
                Contena.Store.get('error').removeApiError(this.error.selfLink);
            }
        },

        createDownloadAssociation(targetId) {
            const blogDownload = this.blogDownloadRepository.create();

            blogDownload.blogId = this.blog.id;
            blogDownload.mediaId = targetId;
            blogDownload.position = this.blog.downloads.length;

            return blogDownload;
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

        updateMediaItemPositions() {
            this.blogMedia.forEach((medium, index) => {
                medium.position = index;
            });
        },

        downloadMedia(download) {
            this.mediaService
                .prepareDownloadMedia(download.media.id)
                .then((downloadConfig) => {
                    if (downloadConfig.type === 'external') {
                        this.triggerDownload(downloadConfig.url);

                        return;
                    }

                    return this.mediaService.downloadMedia(download.media.id).then((data) => {
                        const url = window.URL.createObjectURL(data);
                        this.triggerDownload(url, this.getFileName(download));
                        URL.revokeObjectURL(url);
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('global.sw-media-media-item.notification.downloadError.message'),
                    });
                });
        },

        triggerDownload(url, fileName = null) {
            const link = document.createElement('a');
            link.href = url;

            if (fileName) {
                link.download = fileName;
            } else {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }

            link.dispatchEvent(new MouseEvent('click'));
            link.remove();
        },
    },
};
