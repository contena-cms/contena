import template from './sw-category-detail-blogs.html.twig';
import './sw-category-detail-blogs.scss';

const { Criteria } = Contena.Data;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();
const ContenaError = Contena.Classes.ContenaError;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: ['placeholder'],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            contentStreamFilter: null,
            contentStreamInvalid: false,
            manualAssignedProductsCount: 0,
            parentProducts: [],
        };
    },

    computed: {
        category() {
            return Contena.Store.get('swCategoryDetail').category;
        },

        contentStreamRepository() {
            return this.repositoryFactory.create('content_stream');
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-category.base.blogs.columnNameLabel'),
                    dataIndex: 'name',
                    routerLink: 'sw.blog.detail',
                    sortable: false,
                },
                {
                    property: 'manufacturer.name',
                    label: this.$t('sw-category.base.blogs.columnManufacturerLabel'),
                    routerLink: 'sw.manufacturer.detail',
                    sortable: false,
                },
            ];
        },

        manufacturerColumn() {
            return 'column-manufacturer.name';
        },

        nameColumn() {
            return 'column-name';
        },

        blogCriteria() {
            return new Criteria(1, 10).addAssociation('options.group').addAssociation('manufacturer');
        },

        contentStreamCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('internal', false));
            return criteria;
        },

        contentStreamInvalidError() {
            if (this.contentStreamInvalid) {
                return new ContenaError({
                    code: 'CONTENT_STREAM_INVALID',
                    detail: this.$t('sw-category.base.blogs.dynamicProductGroupInvalidMessage'),
                });
            }
            return null;
        },

        ...mapPropertyErrors('category', ['contentStreamId', 'blogAssignmentType']),

        blogAssignmentTypes() {
            return [
                {
                    value: 'blog',
                    label: this.$t('sw-category.base.blogs.blogAssignmentTypeManualLabel'),
                },
                {
                    value: 'content_stream',
                    label: this.$t('sw-category.base.blogs.blogAssignmentTypeStreamLabel'),
                },
            ];
        },

        dynamicProductGroupHelpText() {
            const link = {
                name: 'sw.blog.stream.index',
            };

            const helpText = this.$t(
                'sw-category.base.blogs.dynamicProductGroupHelpText.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$t('sw-category.base.blogs.dynamicProductGroupHelpText.linkText')}
                       </sw-internal-link>`,
                },
                0,
            );

            try {
                new URL(this.$t('sw-category.base.blogs.dynamicProductGroupHelpText.videoUrl'));
            } catch {
                return helpText;
            }

            return `${helpText}
                    <br>
                    <sw-external-link
                        href="${this.$t('sw-category.base.blogs.dynamicProductGroupHelpText.videoUrl')}">
                        ${this.$t('sw-category.base.blogs.dynamicProductGroupHelpText.videoLink')}
                    </sw-external-link>`;
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },

    watch: {
        'category.contentStreamId'(id) {
            if (!id) {
                this.contentStreamFilter = null;
                return;
            }
            this.loadProductStreamPreview();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.category.contentStreamId) {
                return;
            }
            this.loadProductStreamPreview();
        },

        loadProductStreamPreview() {
            this.contentStreamRepository
                .get(this.category.contentStreamId)
                .then((response) => {
                    this.contentStreamFilter = response.apiFilter;
                    this.contentStreamInvalid = response.invalid;
                })
                .catch(() => {
                    this.contentStreamFilter = null;
                    this.contentStreamInvalid = true;
                });
        },

        onPaginateManualProductAssignment(assignment) {
            this.getParentProducts(assignment);

            this.manualAssignedProductsCount = assignment.total;
        },

        getParentProducts(blogs) {
            const parentIds = blogs.map((blog) => blog.parentId).filter((id) => id !== null);

            if (parentIds.length > 0) {
                const criteria = new Criteria(1, parentIds.length)
                    .addAssociation('manufacturer')
                    .addFilter(Criteria.equalsAny('id', parentIds));

                this.blogRepository.search(criteria).then((parentProducts) => {
                    this.parentProducts = parentProducts;
                });
            }
        },

        getItemName(blog) {
            const name = blog.name ? blog.name : blog.translated.name;
            if (name) {
                return name;
            }

            const parent = this.parentProducts.find((parentProduct) => {
                return parentProduct.id === blog.parentId;
            });

            if (parent) {
                return parent.name ? parent.name : blog.translated.name;
            }

            return null;
        },

        getManufacturer(blog) {
            if (blog.manufacturerId) {
                return blog.manufacturer;
            }

            const parent = this.parentProducts.find((parentProduct) => {
                return parentProduct.id === blog.parentId;
            });

            if (parent && parent.manufacturerId) {
                return parent.manufacturer;
            }

            return null;
        },

        onUpdateProductAssignmentType(value) {
            if (value === 'blog') {
                this.category.contentStreamId = null;
            }
        },
    },
};
