import template from './sw-landing-page-detail-cms.html.twig';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        landingPage() {
            return Contena.Store.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Contena.Store.get('cmsPage').currentPage;
        },
    },
};
