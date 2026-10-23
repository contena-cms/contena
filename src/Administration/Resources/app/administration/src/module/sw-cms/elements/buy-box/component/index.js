import template from './sw-cms-el-buy-box.html.twig';
import './sw-cms-el-buy-box.scss';

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

            if (!this.element.data?.blog) {
                return {
                    name: 'Lorem Ipsum dolor',
                    blogNumber: 'XXXXXX',
                    minPurchase: 1,
                    deliveryTime: {
                        name: '1-3 days',
                    },
                    price: [{ gross: 0.0 }],
                };
            }

            return this.element?.data?.blog ?? null;
        },

        pageType() {
            return this.cmsPageState?.currentPage?.type ?? '';
        },

        isProductPageType() {
            return this.pageType === 'blog_detail';
        },

        alignStyle() {
            if (!this.element.config?.alignment?.value) {
                return null;
            }

            return `justify-content: ${this.element.config.alignment.value};`;
        },

        currentDemoEntity() {
            if (this.cmsPageState.currentMappingEntity === 'blog') {
                return this.cmsPageState.currentDemoEntity;
            }

            return null;
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('buy-box');
            this.initElementData('buy-box');
        },
    },
};
