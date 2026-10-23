/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-blogs-assignment-single-blogs.html.twig';
import './sw-sales-channel-blogs-assignment-single-blogs.scss';

const { Mixin, Filter } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['selection-change'],

    mixins: [Mixin.getByName('notification')],

    props: {
        channel: {
            type: Object,
            required: true,
        },

        containerStyle: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            blogs: [],
            searchTerm: null,
            isLoading: false,
            page: 1,
            limit: 25,
            total: 0,
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria.addAssociation('visibilities.channel');
            criteria.addFilter(
                Criteria.not('and', [Criteria.equals('blog.visibilities.channelId', this.channel.id)]),
            );
            criteria.addFilter(Criteria.equals('parentId', null));

            return criteria;
        },

        blogColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-sales-channel.detail.blogs.columnProductName'),
                    allowResize: true,
                },
                {
                    property: 'blogNumber',
                    label: this.$t('sw-sales-channel.detail.blogs.columnProductNumber'),
                    allowResize: true,
                },
            ];
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    created() {
        this.getProducts();
    },

    methods: {
        getProducts() {
            this.isLoading = true;

            return this.blogRepository
                .search(this.blogCriteria)
                .then((blogs) => {
                    this.blogs = blogs;
                    this.total = blogs.total;
                })
                .catch((error) => {
                    this.blogs = [];
                    this.total = 0;
                    this.createNotificationError({
                        message: error.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;

            if (searchTerm) {
                this.page = 1;
            }

            this.getProducts();
        },

        onSelectionChange(selection) {
            const blogs = Object.values(selection);
            this.$emit('selection-change', blogs, 'singleProducts');
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.blogs.criteria.sortings.forEach(({ field, naturalSorting, order }) => {
                this.blogCriteria.addSorting(Criteria.sort(field, order, naturalSorting));
            });

            this.getProducts();
        },
    },
};
