/*
 * @sw-package inventory
 */

import Criteria from 'src/core/data/criteria.data';
import template from './sw-blog-detail-base.html.twig';
import './sw-blog-detail-base.scss';

const { Context, Utils, Mixin } = Contena;
const { isEmpty } = Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        blogId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showMediaModal: false,
            mediaDefaultFolderId: null,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        isDownloadExternallyManaged() {
            return Contena.Store.get('swProductDetail').isDownloadExternallyManaged;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        customFieldSets() {
            return Contena.Store.get('swProductDetail').customFieldSets;
        },

        loading() {
            return Contena.Store.get('swProductDetail').loading;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `blogType` instead.
         */
        blogStates() {
            return Contena.Store.get('swProductDetail').blogStates;
        },

        blogType() {
            return Contena.Store.get('swProductDetail').blogType;
        },

        isDownloadCardVisible() {
            return this.blogType === 'digital' || this.blogStates.includes('is-download');
        },

        mediaFormVisible() {
            return (
                !this.loading.blog && !this.loading.parentProduct && !this.loading.customFieldSets && !this.loading.media
            );
        },

        blogMediaRepository() {
            return this.repositoryFactory.create(this.blog.media.entity);
        },

        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        mediaDefaultFolderCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', 'blog'));

            return criteria;
        },
    },

    watch: {
        blog() {},
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getMediaDefaultFolderId().then((mediaDefaultFolderId) => {
                this.mediaDefaultFolderId = mediaDefaultFolderId;
            });
        },

        onDownloadExternalManagementChange(value) {
            Contena.Store.get('swProductDetail').isDownloadExternallyManaged = value;
            const errorPath = `blog.${this.blog.id}.downloads`;

            if (value) {
                const error = Contena.Store.get('error').getApiError(this.blog, 'downloads');

                if (error?.code === 'SW_BLOG_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED') {
                    Contena.Store.get('error').removeApiError(errorPath);
                }
                return;
            }

            if (this.blogType === 'digital' && !this.blog.downloads?.length) {
                this.showRequiredDownloadsError();
            }
        },

        showRequiredDownloadsError() {
            Contena.Store.get('error').addApiError({
                expression: `blog.${this.blog.id}.downloads`,
                error: new Contena.Classes.ContenaError({
                    code: 'SW_BLOG_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED',
                    detail: this.$t('sw-blog.detailBase.downloadsExternalManagementRequired'),
                }),
            });
        },

        showProductCard(key) {
            return Contena.Store.get('swProductDetail').showProductCard(key);
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderRepository
                .search(this.mediaDefaultFolderCriteria, {
                    cacheKey: ['media-default-folder', 'blog'],
                })
                .then((mediaDefaultFolder) => {
                    const defaultFolder = mediaDefaultFolder.first();

                    if (defaultFolder.folder?.id) {
                        return defaultFolder.folder.id;
                    }

                    return null;
                });
        },

        mediaRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ id, mediaId, position }) => {
                const media = this.blogMediaRepository.create(Context.api);
                Object.assign(media, {
                    mediaId,
                    position,
                    blogId: this.blog.id,
                });
                if (this.parentProduct.coverId === id) {
                    this.blog.coverId = media.id;
                }

                this.blog.media.push(media);
            });

            this.$refs.blogMediaInheritance.forceInheritanceRemove = true;

            return this.blog.media;
        },

        mediaRestoreInheritanceFunction() {
            this.$refs.blogMediaInheritance.forceInheritanceRemove = false;
            this.blog.coverId = null;

            this.blog.media.getIds().forEach((mediaId) => {
                this.blog.media.remove(mediaId);
            });

            return this.blog.media;
        },

        onOpenMediaModal() {
            this.showMediaModal = true;
        },

        onCloseMediaModal() {
            this.showMediaModal = false;
        },

        onOpenDownloadMediaModal() {
            this.showDownloadMediaModal = true;
        },

        onCloseDownloadMediaModal() {
            this.showDownloadMediaModal = false;
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
        },

        addMedia(media) {
            if (this.isExistingMedia(media)) {
                return Promise.reject(media);
            }

            const newMedia = this.blogMediaRepository.create(Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = {
                url: media.url,
                id: media.id,
            };

            if (isEmpty(this.blog.media) && !this.isSpatial(newMedia)) {
                this.setMediaAsCover(newMedia);
            }

            this.blog.media.add(newMedia);

            return Promise.resolve();
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isSpatial(blogMedia) {
            if (blogMedia.media?.fileExtension === 'glb') {
                return true;
            }
            // we need to check the media url since media.fileExtension is set directly after upload
            return !!blogMedia.media?.url?.endsWith('.glb');
        },

        isExistingMedia(media) {
            return this.blog.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },

        setMediaAsCover(media) {
            media.position = 0;
            this.blog.coverId = media.id;
        },
    },
};
