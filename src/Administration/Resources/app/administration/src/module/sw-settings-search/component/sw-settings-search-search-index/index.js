/**
 * @sw-package inventory
 */
import template from './sw-settings-search-search-index.html.twig';
import './sw-settings-search-search-index.scss';

const BLOG_INDEXER_INTERVAL = 3000;
const { Mixin, Context } = Contena;
const { Criteria } = Contena.Data;

/**
 * @private
 */
export default {
    template,

    inject: ['blogIndexService', 'repositoryFactory', 'acl'],

    emits: ['edit-change'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            isRebuildSuccess: false,
            isRebuildInProgress: false,
            progressBarValue: 0,
            offset: 0,
            syncPolling: null,
            totalProduct: 0,
            latestIndex: null,
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogSearchKeywordRepository() {
            return this.repositoryFactory.create('blog_search_keyword');
        },

        blogCriteria() {
            const blogCriteria = new Criteria(1, 1);
            blogCriteria.addFilter(Criteria.equals('blog.parentId', null));
            return blogCriteria;
        },

        blogSearchKeywordsCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addAggregation(Criteria.max('lastDate', 'createdAt'));
            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = false;
            this.getTotalProduct();
            this.getLatestProductKeywordIndexed();
        },

        beforeDestroyComponent() {
            this.clearPolling();
        },

        getLatestProductKeywordIndexed() {
            this.isLoading = true;
            this.blogSearchKeywordRepository
                .search(this.blogSearchKeywordsCriteria, Context.api)
                .then((result) => {
                    if (!result.total) {
                        return;
                    }

                    this.latestIndex = {
                        lastDate: result.aggregations.lastDate.max,
                    };
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getTotalProduct() {
            this.isLoading = true;
            this.blogRepository
                .search(this.blogCriteria, Context.api)
                .then((result) => {
                    this.totalProduct = result?.total;
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updateProgress() {
            this.blogIndexService
                .index(this.offset)
                .then((response) => {
                    const data = response.data;
                    this.isRebuildSuccess = data.finish;

                    if (this.isRebuildSuccess) {
                        this.clearPolling();
                        this.getLatestProductKeywordIndexed();
                        this.progressBarValue = 100;
                        this.createNotificationSuccess({
                            message: this.$t('sw-settings-search.notification.index.success'),
                        });

                        this.buildFinish();
                    } else {
                        this.progressBarValue = ((this.offset ?? 1) / this.totalProduct) * 100;
                        this.offset = data.offset.offset;
                        this.updateProgress();
                    }
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                    this.isRebuildSuccess = false;
                });
        },

        pollData() {
            if (this.syncPolling === null) {
                this.syncPolling = setTimeout(this.updateProgress, BLOG_INDEXER_INTERVAL);
            }
        },

        clearPolling() {
            if (this.syncPolling !== null) {
                clearTimeout(this.syncPolling);
                this.syncPolling = null;
            }
        },

        rebuildSearchIndex() {
            this.isRebuildInProgress = true;
            this.progressBarValue = 1;
            this.offset = 0;

            this.$emit('edit-change', this.isRebuildInProgress);
            this.pollData();
            this.createNotificationInfo({
                message: this.$t('sw-settings-search.notification.index.started'),
            });
        },

        buildFinish() {
            this.isRebuildSuccess = false;
            this.isRebuildInProgress = false;
            this.progressBarValue = 0;
            this.$emit('edit-change', this.isRebuildInProgress);
        },
    },
};
