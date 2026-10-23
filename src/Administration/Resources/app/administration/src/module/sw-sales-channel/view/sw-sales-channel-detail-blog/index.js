/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-blogs.html.twig';
import './sw-sales-channel-detail-blogs.scss';

const { Mixin, Context } = Contena;
const { EntityCollection, Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'feature', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        channel: {
            type: Object,
            required: false,
            default: () => {},
        },
    },

    data() {
        return {
            blogs: [],
            blogSelection: [],
            isLoading: false,
            searchTerm: null,
            page: 1,
            limit: 25,
            total: 0,
            showProductsModal: false,
            isAssignProductLoading: false,
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogVisibilityRepository() {
            return this.repositoryFactory.create('blog_visibility');
        },

        blogCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTotalCountMode(1);

            criteria.addAssociation('visibilities.channel');
            criteria.addAssociation('options.group');
            criteria.addFilter(Criteria.equals('blog.visibilities.channelId', this.channel.id));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            return criteria;
        },

        blogColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-sales-channel.detail.blogs.columnProductName'),
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: this.$t('sw-sales-channel.detail.blogs.columnActive'),
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'blogNumber',
                    label: this.$t('sw-sales-channel.detail.blogs.columnProductNumber'),
                    allowResize: true,
                },
            ];
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },

    watch: {
        channel: {
            deep: true,
            immediate: true,
            handler(newValue, oldValue) {
                if (!newValue || oldValue?.id === newValue.id) {
                    return;
                }

                this.getProducts();
            },
        },
    },

    methods: {
        getProducts() {
            if (!this.channel?.id) {
                return Promise.reject();
            }

            const context = { ...Context.api };
            context.inheritance = true;

            this.isLoading = true;
            return this.blogRepository
                .search(this.blogCriteria, context)
                .then((blogs) => {
                    this.blogs = blogs;
                    this.total = blogs.total;

                    if (this.total > 0 && this.blogs.length <= 0) {
                        this.page = this.page === 1 ? 1 : this.page - 1;
                        this.getProducts();
                    }
                })
                .catch(() => {
                    this.blogs = [];
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onDeleteProduct(blog) {
            const deleteId = this.getDeleteId(blog);

            return this.blogVisibilityRepository
                .delete(deleteId, Context.api)
                .then(() => {
                    this.getProducts();

                    this.$refs.entityListing.resetSelection();
                })
                .catch((error) => {
                    if (error?.response?.data?.errors) {
                        this.showNotificationError(error.response.data.errors);

                        return;
                    }

                    this.createNotificationError({
                        message: error.message,
                    });
                });
        },

        onDeleteProducts() {
            const deleteIds = Object.values(this.blogSelection).map((blog) => {
                return this.getDeleteId(blog);
            });

            this.isLoading = true;
            return this.blogVisibilityRepository
                .syncDeleted(deleteIds, Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.getProducts();

                    this.$refs.entityListing.resetSelection();
                })
                .catch((error) => {
                    this.isLoading = false;

                    if (error?.response?.data?.data?.blog_visibility?.result) {
                        this.showNotificationError(error.response.data.data.blog_visibility.result);

                        return;
                    }

                    this.createNotificationError({
                        message: error.message,
                    });
                });
        },

        getDeleteId(blog) {
            return blog.visibilities.find((visibility) => {
                return visibility.channelId === this.channel.id;
            }).id;
        },

        showNotificationError(errors) {
            errors.forEach((error) => {
                if (error.errors) {
                    this.showNotificationError(error.errors);
                } else {
                    this.createNotificationError({
                        message: `${error.code}: ${error.detail}`,
                    });
                }
            });
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
            this.blogs.criteria.sortings.forEach(({ field, naturalSorting, order }) => {
                this.blogCriteria.addSorting(Criteria.sort(field, order, naturalSorting));
            });

            this.getProducts();
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;

            if (searchTerm) {
                this.page = 1;
            }

            this.getProducts();
        },

        openAddProductsModal() {
            this.showProductsModal = true;
        },

        onAddProducts(blogs) {
            if (blogs.length <= 0) {
                this.showProductsModal = false;
                return Promise.reject();
            }

            const visibilities = new EntityCollection(
                this.blogVisibilityRepository.route,
                this.blogVisibilityRepository.entityName,
                Context.api,
            );

            blogs.forEach((el) => {
                if (this.blogs?.has(el.id)) {
                    return;
                }

                const visibility = this.blogVisibilityRepository.create(Context.api);
                Object.assign(visibility, {
                    visibility: 30,
                    blogId: el.id,
                    channelId: this.channel.id,
                    channel: this.channel,
                });

                visibilities.add(visibility);
            });

            this.isAssignProductLoading = true;

            return this.saveProductVisibilities(visibilities)
                .then(() => {
                    this.getProducts();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error,
                    });
                })
                .finally(() => {
                    this.showProductsModal = false;
                    this.isAssignProductLoading = false;
                });
        },

        saveProductVisibilities(data) {
            if (data.length <= 0) {
                return Promise.resolve();
            }

            return this.blogVisibilityRepository.saveAll(data, Context.api);
        },

        isProductRemovable(blog) {
            const relevantVisibility = blog.visibilities.find(
                (visibility) => visibility.channelId === this.channel.id,
            );

            return blog.parentId !== relevantVisibility?.blogId;
        },

        onProductSelectionChanged(selection) {
            this.blogSelection = selection;
        },
    },
};
