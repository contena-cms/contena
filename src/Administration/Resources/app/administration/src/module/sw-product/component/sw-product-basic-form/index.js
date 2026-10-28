/*
 * @sw-package inventory
 */

import template from './sw-product-basic-form.html.twig';
import './sw-product-basic-form.scss';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },

        showSettingsInformation: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            productNumberRangeId: null,
            hideCoverImageDescriptionHint: true,
        };
    },

    computed: {
        product() {
            return Contena.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        ...mapPropertyErrors('product', [
            'name',
            'description',
            'productNumber',
            'manufacturerId',
            'active',
            'markAsTopseller',
        ]),

        isTitleRequired() {
            return Contena.Store.get('context').isSystemDefaultLanguage;
        },

        productNumberRangeLink() {
            if (!this.productNumberRangeId) {
                return {
                    name: 'sw.settings.number.range.index',
                };
            }

            return {
                name: 'sw.settings.number.range.detail',
                params: { id: this.productNumberRangeId },
            };
        },

        productNumberHelpText() {
            return this.$t(
                'sw-product.basicForm.productNumberHelpText.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(this.productNumberRangeLink)}
                           :inline="true">
                           ${this.$t('sw-product.basicForm.productNumberHelpText.linkText')}
                       </sw-internal-link>`,
                },
                0,
            );
        },

        highlightHelpText() {
            const themesLink = {
                name: 'sw.theme.manager.index',
            };

            const snippetLink = {
                name: 'sw.settings.snippet.detail',
                params: { key: 'listing.boxLabelTopseller' },
            };

            return this.$t(
                'sw-product.basicForm.highlightHelpText.label',
                {
                    themesLink: `<sw-internal-link
                                 :router-link=${JSON.stringify(themesLink)}
                                 :inline="true">
                                 ${this.$t('sw-product.basicForm.highlightHelpText.themeLinkText')}
                             </sw-internal-link>`,
                    snippetLink: `<sw-internal-link
                                  :router-link=${JSON.stringify(snippetLink)}
                                  :inline="true">
                                  ${this.$t('sw-product.basicForm.highlightHelpText.snippetLinkText')}
                              </sw-internal-link>`,
                },
                0,
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProductNumberRangeId();
            this.loadCoverImageDescriptionHintConfig();
        },

        async loadCoverImageDescriptionHintConfig() {
            const config = (await Contena.Service('userConfigService').search(['product.hideCoverImageDescriptionHint']))
                ?.data?.['product.hideCoverImageDescriptionHint'];

            this.hideCoverImageDescriptionHint = !!config?.value;
        },

        async onCloseCoverImageDescriptionHint() {
            this.hideCoverImageDescriptionHint = true;

            await Contena.Service('userConfigService').upsert({
                'product.hideCoverImageDescriptionHint': {
                    value: true,
                },
            });
        },

        updateIsTitleRequired() {
            this.isTitleRequired = Contena.Context.api.languageId === Contena.Context.api.systemLanguageId;
        },

        getInheritValue(firstKey, secondKey) {
            const p = this.parentProduct;

            if (p[firstKey]) {
                return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
            }
            return null;
        },

        loadProductNumberRangeId() {
            const criteria = new Contena.Data.Criteria(1, 25);

            criteria.addFilter(Contena.Data.Criteria.equals('type.technicalName', 'product'));
            criteria.addFilter(Contena.Data.Criteria.equals('global', true));

            return Contena.Service('repositoryFactory')
                .create('number_range')
                .searchIds(criteria, Contena.Context.api, {
                    cacheKey: ['shared-data', 'number-range-ids', 'product'],
                    ttl: 5 * 60 * 1000,
                })
                .then((numberRangeIds) => {
                    this.productNumberRangeId = numberRangeIds.data[0];
                });
        },
    },
};
