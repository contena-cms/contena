import template from './sw-landing-page-detail-base.html.twig';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        customFieldSetsArray() {
            return Contena.Store.get('swCategoryDetail').customFieldSets ?? [];
        },

        ...mapPropertyErrors('landingPage', ['name', 'url', 'channels']),

        landingPage() {
            return Contena.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Contena.Store.get('cmsPage').currentPage;
        },

        isLayoutSet() {
            return this.landingPage.cmsPageId !== null;
        },
    },
};
