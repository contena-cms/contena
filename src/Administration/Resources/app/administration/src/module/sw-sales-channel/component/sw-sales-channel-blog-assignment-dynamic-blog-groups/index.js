/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-blogs-assignment-dynamic-blog-groups.html.twig';
import './sw-sales-channel-blogs-assignment-dynamic-blog-groups.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['selection-change', 'blog-loading'],

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
            contentStreams: [],
            contentStreamFilter: [],
            isProductStreamsLoading: false,
            isProductLoading: false,
            page: 1,
            limit: 10,
            total: 0,
            term: null,
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        contentStreamRepository() {
            return this.repositoryFactory.create('content_stream');
        },

        blogCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.filters = this.contentStreamFilter;
            criteria.addAssociation('visibilities.channel');
            criteria.addFilter(
                Criteria.not('AND', [Criteria.equals('blog.visibilities.channelId', this.channel.id)]),
            );

            return criteria;
        },

        contentStreamCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(Criteria.equals('internal', false));
            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        contentStreamColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-sales-channel.detail.blogAssignmentModal.dynamicProductGroups.columnName'),
                    sortable: false,
                },
            ];
        },

        /** @deprecated tag:v6.8.0 - Will be removed, use Contena.Filter.getByName('asset') instead. */
        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getProductStreams();
        },

        getProductStreams() {
            this.isProductStreamsLoading = true;

            return this.contentStreamRepository
                .search(this.contentStreamCriteria)
                .then((contentStreams) => {
                    this.contentStreams = contentStreams;
                    this.total = contentStreams.total;
                })
                .catch(() => {
                    this.contentStreams = [];
                    this.total = 0;
                })
                .finally(() => {
                    this.isProductStreamsLoading = false;
                });
        },

        onSearch(term) {
            this.term = term;

            if (term) {
                this.page = 1;
            }

            this.getProductStreams();
        },

        onPaginate(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.getProductStreams();
        },

        onOpen(contentStream) {
            const route = this.$router.resolve({
                name: 'sw.blog.stream.detail',
                params: { id: contentStream.id },
            });

            window.open(route.href, '_blank');
        },

        async onSelect(contentStreams) {
            if (Object.keys(contentStreams).length <= 0) {
                this.$emit('selection-change', [], 'groupProducts');
                return;
            }

            try {
                const blogs = await this.getProductsFromProductStreams(contentStreams);
                this.$emit('selection-change', blogs, 'groupProducts');
            } catch (error) {
                this.createNotificationError({ message: error.message });
            }
        },

        getProductsFromProductStreams(contentStreams) {
            const promises = Object.keys(contentStreams).map((id) => {
                return this.getProductStreamFilter(id).then(() => this.getProducts());
            });

            this.$emit('blog-loading', true);
            this.isProductLoading = true;

            return Promise.all(promises)
                .then((values) => {
                    const blogs = values.flat();
                    return blogs;
                })
                .finally(() => {
                    this.$emit('blog-loading', false);
                    this.isProductLoading = false;
                });
        },

        getProductStreamFilter(id) {
            return this.contentStreamRepository
                .get(id)
                .then((contentStreamFilter) => {
                    this.contentStreamFilter = contentStreamFilter.apiFilter;
                })
                .catch((error) => {
                    this.contentStreamFilter = [];
                    return error;
                });
        },

        getProducts() {
            return this.blogRepository.search(this.blogCriteria).then((blogs) => {
                return blogs;
            });
        },
    },
};
