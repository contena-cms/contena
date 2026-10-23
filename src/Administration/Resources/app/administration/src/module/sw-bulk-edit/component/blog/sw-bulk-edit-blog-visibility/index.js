/**
 * @sw-package inventory
 */
import template from './sw-bulk-edit-blog-visibility.html.twig';

const { Context } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        bulkEditProduct: {
            type: Object,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            displayVisibilityDetail: false,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        blogVisibilityRepository() {
            return this.repositoryFactory.create(this.blog.visibilities.entity);
        },
    },

    methods: {
        visibilitiesRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ blogVersionId, channelId, channel, visibility }) => {
                const visibilities = this.blogVisibilityRepository.create(Context.api);

                Object.assign(visibilities, {
                    blogId: this.blog.id,
                    blogVersionId,
                    channelId,
                    channel,
                    visibility,
                });

                this.blog.visibilities.push(visibilities);
            });

            this.$refs.blogVisibilitiesInheritance.forceInheritanceRemove = true;

            return this.blog.visibilities;
        },
        openModal() {
            this.displayVisibilityDetail = true;
        },

        closeModal() {
            this.displayVisibilityDetail = false;
        },
    },
};
