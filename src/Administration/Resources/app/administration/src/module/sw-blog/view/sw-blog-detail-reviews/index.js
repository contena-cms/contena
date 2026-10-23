/*
 * @sw-package inventory
 */

import template from './sw-blog-detail-reviews.html.twig';
import './sw-blog-detail-reviews.scss';

const { Data, Context } = Contena;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    data() {
        return {
            showReviewDeleteModal: false,
            deleteReviewId: null,
            dataSource: [],
            page: 1,
            limit: 10,
            total: 0,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        cardTitle() {
            return this.total ? this.$t('sw-blog.reviews.cardTitleReviews') : null;
        },

        reviewRepository() {
            return this.repositoryFactory.create('blog_review');
        },

        reviewCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(Criteria.equals('blogId', this.blog.id));
            criteria.setTotalCountMode(1);

            return criteria;
        },

        reviewColumns() {
            return [
                {
                    property: 'points',
                    dataIndex: 'points',
                    label: this.$t('sw-blog.reviewForm.labelPoints'),
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: this.$t('sw-blog.reviewForm.labelStatus'),
                    align: 'center',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: this.$t('sw-blog.reviewForm.labelCreatedAt'),
                },
                {
                    property: 'title',
                    dataIndex: 'title',
                    label: this.$t('sw-blog.reviewForm.labelTitle'),
                    routerLink: 'sw.review.detail',
                },
            ];
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },
    },

    watch: {
        'blog.id': {
            immediate: true,
            handler(newValue) {
                if (!newValue) {
                    return;
                }

                this.getReviews();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getReviews();
        },

        getReviews() {
            if (!this.blog || !this.blog.id) {
                return;
            }

            this.reviewRepository.search(this.reviewCriteria, Context.api).then((reviews) => {
                this.total = reviews.total;
                this.dataSource = reviews;

                if (this.total > 0 && this.dataSource.length <= 0) {
                    this.page = this.page === 1 ? 1 : this.page - 1;
                    this.getReviews();
                }
            });
        },

        onStartReviewDelete(review) {
            this.deleteReviewId = review.id;
            this.onShowReviewDeleteModal();
        },

        onCancelReviewDelete() {
            this.deleteReviewId = null;
            this.onCloseReviewDeleteModal();
        },

        onShowReviewDeleteModal() {
            this.showReviewDeleteModal = true;
        },

        onCloseReviewDeleteModal() {
            this.showReviewDeleteModal = false;
        },

        onConfirmReviewDelete() {
            this.onCloseReviewDeleteModal();

            this.reviewRepository.delete(this.deleteReviewId, Context.api).then(() => {
                this.deleteReviewId = null;
                this.getReviews();
            });
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getReviews();
        },
    },
};
