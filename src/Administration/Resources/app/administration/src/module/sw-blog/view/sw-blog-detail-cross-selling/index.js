/*
 * @sw-package inventory
 */

import template from './sw-blog-detail-cross-selling.html.twig';
import './sw-blog-detail-cross-selling.scss';

const { Criteria, EntityCollection } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            crossSelling: null,
            isInherited: false,
            showRestoreInheritanceModal: false,
        };
    },

    computed: {
        /** @deprecated tag:v6.8.0 - Will be removed, use Contena.Filter.getByName('asset') instead. */
        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        isChild() {
            return Contena.Store.get('swProductDetail').isChild;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        isSystemDefaultLanguage() {
            return Contena.Store.get('context').isSystemDefaultLanguage;
        },

        showCrossSellingCard() {
            return !this.isLoading && this.blog.crossSellings && this.blog.crossSellings.length > 0;
        },

        onAddCrossSellingTooltipMessage() {
            if (this.isSystemDefaultLanguage) {
                return this.$t('sw-privileges.tooltip.warning');
            }

            return this.$t('sw-blog.crossselling.buttonAddCrossSellingLanguageWarning');
        },

        emptyStateDescription() {
            if (!this.isChild) {
                return this.$t('sw-blog.crossselling.emptyStateDescription');
            }

            if (this.isInherited) {
                return this.$t('sw-blog.crossselling.inheritedEmptyStateDescription');
            }

            return this.$t('sw-blog.crossselling.notInheritedEmptyStateDescription');
        },

        parentCrossSellingHref() {
            if (!this.isChild || !this.isInherited) {
                return undefined;
            }

            return this.$router.resolve({
                name: 'sw.blog.detail.crossSelling',
                params: { id: this.blog.parentId },
            }).href;
        },

        crossSellingRepository() {
            return this.repositoryFactory.create(this.blog.crossSellings.entity, this.blog.crossSellings.source);
        },
    },

    watch: {
        blog(blog) {
            blog.crossSellings.forEach((item) => {
                if (item.assignedProducts.length > 0) {
                    return;
                }

                this.loadAssignedProducts(item);
            });
        },

        'blog.crossSellings': {
            handler(value) {
                if (!value) {
                    return;
                }

                this.isInherited = this.isChild && !this.blog.crossSellings.total;
            },
            immediate: true,
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.isInherited = this.isChild && !this.blog.crossSellings.total;
        },

        loadAssignedProducts(crossSelling) {
            const repository = this.repositoryFactory.create(
                crossSelling.assignedProducts.entity,
                crossSelling.assignedProducts.source,
            );

            const criteria = new Criteria(1, 25);
            criteria
                .addFilter(Criteria.equals('crossSellingId', crossSelling.id))
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('blog');

            repository
                .search(criteria, {
                    ...Contena.Context.api,
                    inheritance: true,
                })
                .then((assignedProducts) => {
                    crossSelling.assignedProducts = assignedProducts;
                });

            return crossSelling;
        },

        onAddCrossSelling() {
            this.crossSelling = this.crossSellingRepository.create();
            this.crossSelling.blogId = this.blog.id;
            this.crossSelling.position = this.blog.crossSellings.length + 1;
            this.crossSelling.type = 'contentStream';
            this.crossSelling.sortBy = 'name';
            this.crossSelling.sortDirection = 'ASC';
            this.crossSelling.limit = 24;

            this.blog.crossSellings.push(this.crossSelling);
        },

        restoreInheritance() {
            this.isInherited = true;
        },

        removeInheritance() {
            this.isInherited = false;
        },

        onShowRestoreInheritanceModal() {
            this.showRestoreInheritanceModal = true;
        },

        onCloseRestoreInheritanceModal() {
            this.showRestoreInheritanceModal = false;
        },

        onConfirmRestoreInheritance() {
            this.onCloseRestoreInheritanceModal();

            this.$nextTick(() => {
                this.blog.crossSellings = new EntityCollection(
                    this.crossSellingRepository.route,
                    this.blog.crossSellings.entity,
                    Contena.Context.api,
                );

                this.restoreInheritance();
            });
        },
    },
};
