/*
 * @sw-package inventory
 */

import template from './sw-blog-category-form.html.twig';
import './sw-blog-category-form.scss';

const { Context, Mixin } = Contena;
const { EntityCollection, Criteria } = Contena.Data;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService', 'feature'],

    mixins: [Mixin.getByName('notification')],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            displayVisibilityDetail: false,
            multiSelectVisible: true,
            channel: null,
            defaultVisibility: 30,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        loading() {
            return Contena.Store.get('swProductDetail').loading;
        },

        isChild() {
            return Contena.Store.get('swProductDetail').isChild;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        ...mapPropertyErrors('blog', ['tags', 'active']),

        hasSelectedVisibilities() {
            if (this.blog && this.blog.visibilities) {
                return this.blog.visibilities.length > 0;
            }
            return false;
        },

        blogVisibilityRepository() {
            return this.repositoryFactory.create(this.blog.visibilities.entity);
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.channel = new EntityCollection(
                '/sales-channel',
                'channel',
                Contena.Context.api,
                new Criteria(1, 25),
            );
        },

        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;
        },

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
    },
};
