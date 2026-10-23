import template from './sw-cms-el-blog-description-reviews.html.twig';
import './sw-cms-el-blog-description-reviews.scss';

const { Mixin } = Contena;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [Mixin.getByName('cms-element'), Mixin.getByName('placeholder')],

    computed: {
        blog() {
            if (this.currentDemoEntity) {
                return this.currentDemoEntity;
            }

            if (!this.element.data || !this.element.data.blog) {
                return {
                    name: 'Blog information',
                    description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod
                                  tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.
                                  At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren,
                                  no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet,
                                  consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et
                                  dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo
                                  dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem
                                  ipsum dolor sit amet.`,
                };
            }

            return this.element.data.blog;
        },

        pageType() {
            return this.cmsPageState?.currentPage?.type;
        },

        isProductPageType() {
            return this.pageType === 'blog_detail';
        },

        alignStyle() {
            if (!this.element.config.alignment || !this.element.config.alignment.value) {
                return null;
            }

            return `align-content: ${this.element.config.alignment.value};`;
        },

        currentDemoEntity() {
            if (this.cmsPageState.currentMappingEntity === 'blog') {
                return this.cmsPageState.currentDemoEntity;
            }

            return null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-description-reviews');
            this.initElementData('blog-description-reviews');
        },
    },
};
