/*
 * @sw-package inventory
 */

import template from './sw-blog-price-form.html.twig';
import './sw-blog-price-form.scss';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            displayMaintainCurrencies: false,
        };
    },

    computed: {
        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        defaultPrice() {
            return Contena.Store.get('swProductDetail').defaultPrice;
        },

        defaultCurrency() {
            return Contena.Store.get('swProductDetail').defaultCurrency;
        },

        blogTaxRate() {
            return Contena.Store.get('swProductDetail').blogTaxRate;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        taxes() {
            return Contena.Store.get('swProductDetail').taxes;
        },

        currencies() {
            return Contena.Store.get('swProductDetail').currencies;
        },

        ...mapPropertyErrors('blog', ['taxId', 'price', 'purchasePrices']),

        taxRateHelpText() {
            const link = {
                name: 'sw.settings.tax.index',
            };

            return this.$t(
                'sw-blog.priceForm.taxRateHelpText.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$t('sw-blog.priceForm.taxRateHelpText.linkText')}
                      </sw-internal-link>`,
                },
                0,
            );
        },

        prices: {
            get() {
                const prices = {
                    price: [],
                    purchasePrices: [],
                };

                if (this.blog && Array.isArray(this.blog.price)) {
                    prices.price = [...this.blog.price];
                }

                if (this.blog && Array.isArray(this.blog.purchasePrices)) {
                    prices.purchasePrices = [...this.blog.purchasePrices];
                }

                return prices;
            },

            set(newValue) {
                this.blog.price = newValue?.price || null;
                this.blog.purchasePrices = newValue?.purchasePrices || null;
            },
        },

        parentPrices() {
            return {
                price: this.blog.price || this.parentProduct.price,
                purchasePrices: this.blog.purchasePrices || this.parentProduct.purchasePrices,
            };
        },

        taxRateOptions() {
            return this.taxes.map((tax) => {
                return {
                    id: tax.id,
                    value: tax.id,
                    label: this.getTaxLabel(tax),
                };
            });
        },
    },

    methods: {
        removePriceInheritation(refPrice) {
            const defaultRefPrice = refPrice.price?.find((price) => price.currencyId === this.defaultCurrency.id);
            const defaultRefPurchasePrice = refPrice.purchasePrices?.find(
                (price) => price.currencyId === this.defaultCurrency.id,
            );

            const prices = {
                price: [],
                purchasePrices: [],
            };

            if (defaultRefPrice) {
                prices.price.push({
                    currencyId: defaultRefPrice.currencyId,
                    gross: defaultRefPrice.gross,
                    net: defaultRefPrice.net,
                    linked: defaultRefPrice.linked,
                    listPrice: defaultRefPrice.listPrice ? defaultRefPrice.listPrice : null,
                    regulationPrice: defaultRefPrice.regulationPrice ? defaultRefPrice.regulationPrice : null,
                });
            }

            if (defaultRefPurchasePrice) {
                prices.purchasePrices.push({
                    currencyId: defaultRefPurchasePrice.currencyId,
                    gross: defaultRefPurchasePrice.gross,
                    net: defaultRefPurchasePrice.net,
                    linked: defaultRefPurchasePrice.linked,
                });
            }

            return prices;
        },

        inheritationCheckFunction() {
            return !this.prices.price.length && !this.prices.purchasePrices.length;
        },

        onMaintainCurrenciesClose(prices) {
            this.blog.price = prices;

            this.displayMaintainCurrencies = false;
        },

        getTaxLabel(tax) {
            if (this.$te(`global.tax-rates.${tax.name}`)) {
                return this.$t(`global.tax-rates.${tax.name}`);
            }

            return tax.name;
        },

        updatePrices(index) {
            this.blog.price.splice(index, 1);
        },
    },
};
