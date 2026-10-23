/**
 * @sw-package inventory
 */
import template from './sw-bulk-edit-blog-media.html.twig';

const { Utils, Mixin } = Contena;
const { Criteria } = Contena.Data;
const { isEmpty } = Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
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

        blogMediaRepository() {
            return this.repositoryFactory.create('blog_media');
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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadMediaDefaultFolder();
        },

        loadMediaDefaultFolder() {
            this.getMediaDefaultFolderId().then((mediaDefaultFolderId) => {
                this.mediaDefaultFolderId = mediaDefaultFolderId;
            });
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderRepository
                .search(this.mediaDefaultFolderCriteria, {
                    cacheKey: ['media-default-folder', 'blog'],
                })
                .then((mediaDefaultFolder) => {
                    const defaultFolder = mediaDefaultFolder.first();
                    if (defaultFolder === null) {
                        return null;
                    }

                    if (defaultFolder.folder?.id) {
                        return defaultFolder.folder.id;
                    }

                    return null;
                });
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

            const newMedia = this.blogMediaRepository.create(Contena.Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = {
                url: media.url,
                id: media.id,
            };

            this.blog.media.add(newMedia);

            return Promise.resolve();
        },

        isExistingMedia(media) {
            return this.blog.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },
    },
};
