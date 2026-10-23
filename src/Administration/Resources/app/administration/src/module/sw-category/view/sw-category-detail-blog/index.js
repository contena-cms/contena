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
            blogStreamFilter: null,
            blogStreamInvalid: false,
            manualAssignedProductsCount: 0,
            parentProducts: [],
        };
    },

    computed: {
        category() {
            return Contena.Store.get('swCategoryDetail').category;
        },

        blogStreamRepository() {
            return this.repositoryFactory.create('blog_stream');
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

        blogStreamCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('internal', false));
            return criteria;
        },

        blogStreamInvalidError() {
            if (this.blogStreamInvalid) {
                return new ContenaError({
                    code: 'BLOG_STREAM_INVALID',
                    detail: this.$t('sw-category.base.blogs.dynamicProductGroupInvalidMessage'),
                });
            }
            return null;
        },

        ...mapPropertyErrors('category', ['blogStreamId', 'blogAssignmentType']),

        blogAssignmentTypes() {
            return [
                {
                    value: 'blog',
                    label: this.$t('sw-category.base.blogs.blogAssignmentTypeManualLabel'),
                },
                {
                    value: 'blog_stream',
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
        'category.blogStreamId'(id) {
            if (!id) {
                this.blogStreamFilter = null;
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
            if (!this.category.blogStreamId) {
                return;
            }
            this.loadProductStreamPreview();
        },

        loadProductStreamPreview() {
            this.blogStreamRepository
                .get(this.category.blogStreamId)
                .then((response) => {
                    this.blogStreamFilter = response.apiFilter;
                    this.blogStreamInvalid = response.invalid;
                })
                .catch(() => {
                    this.blogStreamFilter = null;
                    this.blogStreamInvalid = true;
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
                this.category.blogStreamId = null;
            }
        },
    },
};
