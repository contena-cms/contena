import template from './sw-blog-stream-list.html.twig';
import './sw-blog-stream-list.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

/**
 * @sw-package inventory
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data() {
        return {
            blogStreams: null,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            isLoading: false,
            showDeleteModal: false,
            searchConfigEntity: 'blog_stream',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        blogStreamRepository() {
            return this.repositoryFactory.create('blog_stream');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },
    },

    methods: {
        onInlineEditSave(promise, blogStream) {
            return promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-blog-stream.detail.messageSaveSuccess', { name: blogStream.name }, 0),
                    });
                })
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$t('sw-blog-stream.detail.messageSaveError'),
                    });
                });
        },

        onChangeLanguage() {
            return this.getList();
        },

        async getList() {
            this.isLoading = true;

            let criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(Criteria.equals('internal', false));
            criteria.setTerm(this.term);
            if (this.acl.can('category:read')) {
                criteria.addAggregation(
                    Criteria.terms(
                        'blog_stream',
                        'id',
                        null,
                        null,
                        Criteria.count('categories', 'blog_stream.categories.id'),
                    ),
                );
            }
            this.naturalSorting = this.sortBy === 'createdAt';
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            criteria = await this.addQueryScores(this.term, criteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            return this.blogStreamRepository
                .search(criteria)
                .then((items) => {
                    this.total = items.total;
                    this.blogStreams = items;
                    this.isLoading = false;

                    return items;
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        getProductStreamColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    inlineEdit: 'string',
                    label: 'sw-blog-stream.list.columnName',
                    routerLink: 'sw.blog.stream.detail',
                    width: '250px',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'description',
                    label: 'sw-blog-stream.list.columnDescription',
                    width: '250px',
                    allowResize: true,
                },
                {
                    property: 'updatedAt',
                    label: 'sw-blog-stream.list.columnDateUpdated',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'invalid',
                    label: 'sw-blog-stream.list.columnStatus',
                    allowResize: true,
                },
            ];
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$t('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role) || this.allowDelete,
            };
        },

        onDeleteItemFailed({ id, errorResponse }) {
            const stream = this.blogStreams?.get(id);
            const message = errorResponse?.response?.data?.errors?.[0]?.detail || null;

            if (!stream) {
                return;
            }

            if (!this.blogStreams.aggregations.blog_stream) {
                this.createNotificationError({ message });
                return;
            }

            const aggregation = this.blogStreams.aggregations.blog_stream.buckets
                .filter((bucket) => {
                    return bucket.key === id;
                })
                .at(0);

            const count = aggregation.categories.count;
            const name = stream.name;

            if (count === 0) {
                this.createNotificationError({ message });
                return;
            }

            this.createNotificationError({
                message: this.$t('sw-blog-stream.general.errorCategory', { name, count }, count),
            });
        },

        onDeleteItemsFailed({ selectedIds, errorResponse }) {
            selectedIds.forEach((id) => {
                this.onDeleteItemFailed({ id, errorResponse });
            });
        },

        onDuplicate(item) {
            const behavior = {
                cloneChildren: true,
                overwrites: {
                    name: `${item.name || item.translated.name} ${this.$t('global.default.copy')}`,
                },
            };

            this.isLoading = true;

            this.blogStreamRepository
                .clone(item.id, behavior, Contena.Context.api)
                .then((clone) => {
                    const route = {
                        name: 'sw.blog.stream.detail',
                        params: { id: clone.id },
                    };

                    this.$router.push(route);
                })
                .catch(() => {
                    this.isLoading = false;

                    this.createNotificationError({
                        message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                    });
                });
        },
    },
};
