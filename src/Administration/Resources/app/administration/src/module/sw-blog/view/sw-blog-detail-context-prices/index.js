/*
 * @sw-package inventory
 */

import template from './sw-blog-detail-context-prices.html.twig';
import './sw-blog-detail-context-prices.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [Mixin.getByName('notification')],

    props: {
        isSetDefaultPrice: {
            type: Boolean,
            required: false,
            default: false,
        },
        canSetLoadingRules: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            rules: [],
            totalRules: 0,
            isInherited: false,
            showListPrices: {},
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

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        taxes() {
            return Contena.Store.get('swProductDetail').taxes;
        },

        currencies() {
            return Contena.Store.get('swProductDetail').currencies;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        defaultCurrency() {
            return Contena.Store.get('swProductDetail').defaultCurrency;
        },

        defaultPrice() {
            return Contena.Store.get('swProductDetail').defaultPrice;
        },

        blogTaxRate() {
            return Contena.Store.get('swProductDetail').blogTaxRate;
        },

        isChild() {
            return Contena.Store.get('swProductDetail').isChild;
        },

        priceRepository() {
            if (this.blog && this.blog.prices) {
                return this.repositoryFactory.create(this.blog.prices.entity, this.blog.prices.source);
            }
            return null;
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        priceRuleGroups() {
            const priceRuleGroups = {};

            if (!this.blog.prices) {
                return priceRuleGroups;
            }

            if (!this.rules) {
                return priceRuleGroups;
            }

            const sortedPrices = this.blog.prices.toSorted((a, b) => {
                const aRule = this.findRuleById(a.ruleId);
                const bRule = this.findRuleById(b.ruleId);

                if (!aRule || !aRule.name || !bRule || !bRule.name) {
                    return 0;
                }

                return aRule.name > bRule.name ? 1 : -1;
            });

            sortedPrices.forEach((rule) => {
                if (!priceRuleGroups[rule.ruleId]) {
                    priceRuleGroups[rule.ruleId] = {
                        ruleId: rule.ruleId,
                        rule: this.findRuleById(rule.ruleId),
                        prices: this.findPricesByRuleId(rule.ruleId),
                    };
                }
            });

            // Sort prices
            Object.values(priceRuleGroups).forEach((priceRule) => {
                priceRule.prices.sort((a, b) => {
                    return a.quantityStart - b.quantityStart;
                });
            });

            return priceRuleGroups;
        },

        priceRuleGroupsExists() {
            return Object.values(this.priceRuleGroups).length > 0;
        },

        canAddPriceRule() {
            const usedRules = Object.keys(this.priceRuleGroups).length;
            const availableRules = this.rules.length;

            return usedRules !== availableRules;
        },

        emptyPriceRuleExists() {
            return typeof this.priceRuleGroups.null !== 'undefined';
        },

        isLoaded() {
            return !this.isLoading && this.currencies && this.taxes && this.blog;
        },

        currencyColumns() {
            this.sortCurrencies();

            return this.currencies.map((currency) => {
                return {
                    property: `price-${currency.isoCode}`,
                    label: currency.translated.name || currency.name,
                    visible: true,
                    allowResize: true,
                    primary: false,
                    rawData: false,
                    width: 'calc(var(--scale-size-224) + var(--scale-size-24) + var(--scale-size-22))',
                    multiLine: true,
                };
            });
        },

        pricesColumns() {
            const priceColumns = [
                {
                    property: 'quantityStart',
                    label: 'sw-blog.advancedPrices.columnFrom',
                    visible: true,
                    allowResize: true,
                    primary: true,
                    rawData: false,
                    width: 'calc(var(--scale-size-80) + var(--scale-size-40))',
                },
                {
                    property: 'quantityEnd',
                    label: 'sw-blog.advancedPrices.columnTo',
                    visible: true,
                    allowResize: true,
                    primary: true,
                    rawData: false,
                    width: 'calc(var(--scale-size-80) + var(--scale-size-40))',
                },
                {
                    property: 'type',
                    label: 'sw-blog.advancedPrices.columnType',
                    visible: true,
                    allowResize: true,
                    width: 'calc(var(--scale-size-224) + var(--scale-size-26))',
                    multiLine: true,
                },
            ];

            return [...priceColumns, ...this.currencyColumns];
        },

        emptyStateDescription() {
            if (!this.isChild) {
                return this.$t('sw-blog.advancedPrices.advancedPricesNotExisting');
            }

            if (this.isInherited) {
                return this.$t('sw-blog.advancedPrices.advancedPricesInherited');
            }

            return this.$t('sw-blog.advancedPrices.advancedPricesNotInherited');
        },

        parentPricesHref() {
            if (!this.isChild || !this.isInherited) {
                return undefined;
            }

            return this.$router.resolve({
                name: 'sw.blog.detail.prices',
                params: { id: this.blog.parentId },
            }).href;
        },
    },

    watch: {
        'blog.prices': {
            handler(value) {
                if (!value) {
                    return;
                }

                this.isInherited = this.isChild && !this.blog.prices.total;
            },
            immediate: true,
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const ruleCriteria = new Criteria(1, 500);
            ruleCriteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null),
                ]),
            );

            if (this.canSetLoadingRules) {
                Contena.Store.get('swProductDetail').setLoading(['rules', true]);
            }
            this.ruleRepository.search(ruleCriteria).then((res) => {
                this.rules = res;
                this.totalRules = res.total;

                Contena.Store.get('swProductDetail').setLoading(['rules', false]);
            });

            this.isInherited = this.isChild && !this.blog.prices.total;
        },

        sortCurrencies() {
            this.currencies.sort((a, b) => {
                if (a.isSystemDefault) {
                    return -1;
                }
                if (b.isSystemDefault) {
                    return 1;
                }
                if (a.translated.name < b.translated.name) {
                    return -1;
                }
                if (a.translated.name > b.translated.name) {
                    return 1;
                }
                return 0;
            });
        },

        onRuleChange(value, ruleId) {
            const changeRules = () => {
                this.blog.prices.forEach((priceRule) => {
                    if (priceRule.ruleId === ruleId) {
                        priceRule.ruleId = value;
                    }
                });
            };

            /*
             * Adding a $nextTick here for the case when the admin_user creates a new rule.
             * this $nextTick is needed because vue first needs to remove the modal from the DOM.
             * without it this would not happen.
             */
            this.$nextTick(() => {
                changeRules();
            });
        },

        async onAddNewPriceGroup(ruleId = null) {
            /*
             * Adding a nextTick to wait until DOM has been updated. We do this because of the rule create modal.
             * Otherwise the modal won't disappear.
             */
            await this.$nextTick();

            if (this.emptyPriceRuleExists) {
                return;
            }

            const newPriceRule = this.priceRepository.create();

            newPriceRule.ruleId = ruleId;
            newPriceRule.blogId = this.blog.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = this.defaultCurrency.id;
            newPriceRule.price = [
                {
                    currencyId: this.defaultCurrency.id,
                    gross: this.isSetDefaultPrice ? 0 : this.defaultPrice.gross,
                    linked: this.defaultPrice.linked,
                    net: this.isSetDefaultPrice ? 0 : this.defaultPrice.net,
                    listPrice: null,
                },
            ];

            if (this.defaultPrice.listPrice) {
                newPriceRule.price[0].listPrice = {
                    currencyId: this.defaultCurrency.id,
                    gross: this.defaultPrice.listPrice.gross,
                    linked: this.defaultPrice.listPrice.linked,
                    net: this.defaultPrice.listPrice.net,
                };
            }

            this.blog.prices.add(newPriceRule);

            await this.$nextTick();

            const scrollableArea = this.$parent.$el.children.item(0);

            if (scrollableArea) {
                scrollableArea.scrollTo({
                    top: scrollableArea.scrollHeight,
                    behavior: 'smooth',
                });
            }
        },

        onPriceGroupDelete(ruleId) {
            const allPriceRules = this.blog.prices.map((priceRule) => {
                return { id: priceRule.id, ruleId: priceRule.ruleId };
            });

            allPriceRules.forEach((priceRule) => {
                if (ruleId !== priceRule.ruleId) {
                    return;
                }

                this.blog.prices.remove(priceRule.id);
            });
        },

        onPriceGroupDuplicate(priceGroup) {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            // duplicate each price rule
            priceGroup.prices.forEach((price) => {
                this.duplicatePriceRule(price, null);
            });
        },

        onPriceRuleDelete(priceRule) {
            // get the priceRuleGroup for the priceRule
            const matchingPriceRuleGroup = this.priceRuleGroups[priceRule.ruleId];

            // if it is the only item in the priceRuleGroup
            if (matchingPriceRuleGroup.prices.length <= 1) {
                this.createNotificationError({
                    message: this.$t('sw-blog.advancedPrices.deletionNotPossibleMessage'),
                });

                return;
            }

            // get actual rule index
            const actualRuleIndex = matchingPriceRuleGroup.prices.indexOf(priceRule);

            // if it is the last item
            if (typeof priceRule.quantityEnd === 'undefined' || priceRule.quantityEnd === null) {
                // get previous rule
                const previousRule = matchingPriceRuleGroup.prices[actualRuleIndex - 1];

                // set the quantityEnd from the previous rule to null
                previousRule.quantityEnd = null;
            } else {
                // get next rule
                const nextRule = matchingPriceRuleGroup.prices[actualRuleIndex + 1];

                // set the quantityStart from the next rule to the quantityStart from the actual rule
                nextRule.quantityStart = priceRule.quantityStart;
            }

            // delete rule
            this.blog.prices.remove(priceRule.id);
        },

        onInheritanceRestore(rule, currency) {
            // remove price from rule.price with the currency id
            const indexOfPrice = rule.price.findIndex((price) => price.currencyId === currency.id);
            rule.price.splice(indexOfPrice, 1);
        },

        onInheritanceRemove(rule, currency) {
            // create new price based on the default price
            const defaultPrice = this.findDefaultPriceOfRule(rule);
            const newPrice = {
                currencyId: currency.id,
                gross: this.convertPrice(defaultPrice.gross, currency),
                linked: defaultPrice.linked,
                net: this.convertPrice(defaultPrice.net, currency),
                listPrice: null,
            };

            if (defaultPrice.listPrice) {
                newPrice.listPrice = {
                    currencyId: currency.id,
                    gross: this.convertPrice(defaultPrice.listPrice.gross, currency),
                    linked: defaultPrice.listPrice.linked,
                    net: this.convertPrice(defaultPrice.listPrice.net, currency),
                };
            }

            // add price to rule.price
            rule.price[rule.price.length] = newPrice;
        },

        isPriceFieldInherited(rule, currency) {
            return rule.price.findIndex((price) => price.currencyId === currency.id) < 0;
        },

        convertPrice(value, currency) {
            const calculatedPrice = value * currency.factor;
            const priceRounded = calculatedPrice.toFixed(currency.decimalPrecision);
            return Number(priceRounded);
        },

        findRuleById(ruleId) {
            return this.rules.find((rule) => {
                return rule.id === ruleId;
            });
        },

        findPricesByRuleId(ruleId) {
            return this.blog.prices.filter((item) => {
                return item.ruleId === ruleId;
            });
        },

        findDefaultPriceOfRule(rule) {
            return rule.price.find((price) => price.currencyId === this.defaultCurrency.id);
        },

        onQuantityEndChange(price, priceGroup) {
            if (price.quantityEnd !== null && price.quantityEnd < price.quantityStart) {
                price.quantityEnd = null;
                return;
            }
            // when not last price
            if (priceGroup.prices.indexOf(price) + 1 !== priceGroup.prices.length) {
                return;
            }

            this.createPriceRule(priceGroup);
        },

        createPriceRule(priceGroup) {
            // create new price rule
            const newPriceRule = this.priceRepository.create();
            newPriceRule.blogId = this.blog.id;
            newPriceRule.ruleId = priceGroup.ruleId;

            const highestEndValue = Math.max(...priceGroup.prices.map((price) => price.quantityEnd));
            newPriceRule.quantityStart = highestEndValue + 1;

            newPriceRule.price = [
                {
                    currencyId: this.defaultCurrency.id,
                    gross: this.defaultPrice.gross,
                    linked: this.defaultPrice.linked,
                    net: this.defaultPrice.net,
                    listPrice: null,
                },
            ];

            if (this.defaultPrice.listPrice) {
                newPriceRule.price[0].listPrice = {
                    currencyId: this.defaultCurrency.id,
                    gross: this.defaultPrice.listPrice ? this.defaultPrice.listPrice.gross : null,
                    linked: this.defaultPrice.listPrice ? this.defaultPrice.listPrice.linked : true,
                    net: this.defaultPrice.listPrice ? this.defaultPrice.listPrice.net : null,
                };
            }

            this.blog.prices.add(newPriceRule);
        },

        canCreatePriceRule(priceGroup) {
            const emptyPrices = priceGroup.prices.filter((price) => {
                return !price.quantityEnd;
            });

            return !!emptyPrices.length;
        },

        duplicatePriceRule(referencePrice, ruleId = null) {
            const newPriceRule = this.priceRepository.create();

            newPriceRule.blogId = referencePrice.blogId;
            newPriceRule.quantityEnd = referencePrice.quantityEnd;
            newPriceRule.quantityStart = referencePrice.quantityStart;
            newPriceRule.ruleId = ruleId;

            // add prices
            newPriceRule.price = [];

            referencePrice.price.forEach((price, index) => {
                newPriceRule.price[index] = { ...price };
            });

            this.blog.prices.add(newPriceRule);
        },

        getPriceRuleGroupClass(number) {
            return [`context-price-group-${number}`];
        },

        restoreInheritance() {
            this.isInherited = true;
        },

        removeInheritance() {
            this.isInherited = false;
        },

        onChangeShowListPrices(value, ruleId) {
            this.showListPrices[ruleId] = value;
        },

        getStartQuantityTooltip(itemIndex, quantity) {
            return {
                message: this.$t('sw-blog.advancedPrices.advancedPriceDisabledTooltip'),
                width: 275,
                showDelay: 200,
                disabled: itemIndex !== 0 || quantity !== 1,
            };
        },
    },
};
