/*
 * @sw-package inventory
 */

import template from './sw-blog-cross-selling-assignment.html.twig';
import './sw-blog-cross-selling-assignment.scss';

const { Context } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        assignedProducts: {
            type: Array,
            required: true,
        },

        crossSellingId: {
            type: String,
            required: true,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            isLoadingData: false,
            variantNames: {},
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        isLoadingGrid() {
            return this.isLoadingData || this.isLoading;
        },

        assignmentRepository() {
            return this.repositoryFactory.create(this.assignedProducts.entity, this.assignedProducts.source);
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        searchCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.blog.id)]));
            criteria.addAssociation('options.group');

            return criteria;
        },

        searchContext() {
            return {
                ...Context.api,
                inheritance: true,
            };
        },

        total() {
            if (!this.assignedProducts || !Array.isArray(this.assignedProducts)) {
                return 0;
            }

            return this.assignedProducts.length;
        },

        assignedProductColumns() {
            return [
                {
                    property: 'blog.translated.name',
                    label: this.$t('sw-blog.list.columnName'),
                    primary: true,
                    allowResize: true,
                    sortable: false,
                },
                {
                    property: 'blog.blogNumber',
                    label: this.$t('sw-blog.list.columnProductNumber'),
                    allowResize: true,
                    sortable: false,
                },
                {
                    property: 'position',
                    label: this.$t('sw-blog.crossselling.inputCrossSellingPosition'),
                    allowResize: true,
                    sortable: false,
                },
            ];
        },

        variantProductIds() {
            const variantProductIds = [];

            this.assignedProducts.forEach((item) => {
                if (!item.blog.parentId || item.blog.translated.name || item.blog.name) {
                    return;
                }

                variantProductIds.push(item.blog.id);
            });

            return variantProductIds;
        },

        variantCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.setIds(this.variantProductIds);

            return criteria;
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },

    created() {
        if (this.variantProductIds.length === 0) {
            return;
        }

        this.blogRepository.search(this.variantCriteria, { ...Context.api, inheritance: true }).then((variants) => {
            const variantNames = {};
            variants.forEach((variant) => {
                variantNames[variant.id] = variant.translated.name;
            });
            this.variantNames = variantNames;
        });
    },

    methods: {
        onToggleProduct(blogId) {
            if (blogId === null) {
                return;
            }

            this.isLoadingData = true;
            const matchedAssignedProduct = this.assignedProducts.find((assignedProduct) => {
                return assignedProduct.blogId === blogId;
            });

            if (matchedAssignedProduct) {
                this.removeItem(matchedAssignedProduct);
                this.isLoadingData = false;
            } else {
                const newProduct = this.assignmentRepository.create();
                newProduct.crossSellingId = this.crossSellingId;
                newProduct.blogId = blogId;
                newProduct.position = this.assignedProducts.length + 1;
                this.assignedProducts.add(newProduct);

                const criteria = new Criteria(1, 25);
                criteria.addAssociation('options.group');

                this.blogRepository.get(blogId, { ...Context.api, inheritance: true }, criteria).then((blog) => {
                    newProduct.blog = blog;
                    this.isLoadingData = false;
                });
            }
        },

        removeItem(item) {
            const oldPosition = item.position;

            this.assignedProducts.remove(item.id);
            this.assignedProducts.forEach((assignedProduct) => {
                if (assignedProduct.position <= oldPosition) {
                    return;
                }

                assignedProduct.position -= 1;
            });
        },

        isSelected(item) {
            return this.assignedProducts.some((p) => p.blogId === item.id);
        },
    },
};
