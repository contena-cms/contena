/*
 * @sw-package inventory
 */

import template from './sw-blog-clone-modal.html.twig';
import './sw-blog-clone-modal.scss';

const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'numberRangeService'],

    emits: ['clone-finish'],

    props: {
        blog: {
            type: Object,
            required: true,
        },
        blogNumberPreview: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            cloningVariants: false,
            cloneMaxProgress: 0,
            cloneProgress: 0,
        };
    },

    computed: {
        // @deprecated tag:v6.8.0 - Will be removed, no longer needed
        progressInPercentage() {
            return 100 / (this.cloneMaxProgress * this.cloneProgress);
        },

        repository() {
            return this.repositoryFactory.create('blog');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.duplicate();
        },

        async duplicate() {
            if (this.blogNumberPreview === this.blog.blogNumber) {
                const number = await this.numberRangeService.reserve('blog');
                this.blog.blogNumber = number.number;
            }

            await this.repository.save(this.blog);

            const number = await this.numberRangeService.reserve('blog');
            const duplicate = await this.cloneParent(number);

            this.verifyVariants(duplicate);
        },

        async cloneParent(number) {
            // Create shallow copy to prevent changing the original blog
            const variantListingConfigOverwrite = cloneDeep(this.blog.variantListingConfig);
            if (variantListingConfigOverwrite && variantListingConfigOverwrite.mainVariantId) {
                variantListingConfigOverwrite.mainVariantId = null;
            }

            const behavior = {
                cloneChildren: false,
                overwrites: {
                    blogNumber: number.number,
                    name: `${this.blog.name} ${this.$t('global.default.copy')}`,
                    active: false,
                    mainVariantId: null,
                    canonicalProductId: null,
                    variantListingConfig: variantListingConfigOverwrite,
                },
            };

            const clone = await this.repository.clone(this.blog.id, behavior, Contena.Context.api);

            return { id: clone.id, blogNumber: number.number };
        },

        verifyVariants(duplicate) {
            this.getChildrenIds().then((ids) => {
                if (ids.length <= 0) {
                    this.$emit('clone-finish', { id: duplicate.id });
                    return;
                }

                this.cloningVariants = true;

                this.cloneProgress = 1;
                this.cloneMaxProgress = ids.length;

                this.duplicateVariant(duplicate, ids, () => {
                    this.cloningVariants = false;
                    this.$emit('clone-finish', { id: duplicate.id });
                });
            });
        },

        getChildrenIds() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('parentId', this.blog.id));

            return this.repository.searchIds(criteria).then((response) => {
                return response.data;
            });
        },

        duplicateVariant(duplicate, ids, callback) {
            if (ids.length <= 0) {
                callback();
                return;
            }
            const id = ids.shift();

            const behavior = {
                overwrites: {
                    parentId: duplicate.id,
                    blogNumber: `${duplicate.blogNumber}.${this.cloneProgress}`,
                    canonicalProductId: null,
                },
                cloneChildren: false,
            };

            this.repository.clone(id, behavior, Contena.Context.api).then(() => {
                this.cloneProgress += 1;
                this.duplicateVariant(duplicate, ids, callback);
            });
        },
    },
};
