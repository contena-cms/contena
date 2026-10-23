/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-country-general.html.twig';
import './sw-settings-country-general.scss';

const { Component, Mixin } = Contena;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    emits: ['modal-save'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('country'),
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        admin_userConfig: {
            type: Object,
            required: true,
        },
        admin_userConfigValues: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            showCurrencyModal: false,
            currencyDependsValue: [],
            currencies: [],
            menuOptions: [],
            taxFreeType: '',
            countryId: '',
            baseCurrencyId: '',
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        isVatIdFormatCheckWithoutEffect() {
            return !this.country.vatIdPattern && !this.country.isEu;
        },

        ...mapPropertyErrors('country', ['name']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }
            this.countryId = this.$route.params.id.toLowerCase();
            this.loadCurrencies();
        },

        loadCurrencies() {
            return this.currencyRepository.search(new Criteria(1, 500), Contena.Context.api).then((currencies) => {
                this.currencies = currencies;
            });
        },

        openCustomerTaxModal() {
            this.onToggleCurrencyModal('userTax');
        },

        openCompanyTaxModal() {
            this.onToggleCurrencyModal('companyTax');
        },

        onToggleCurrencyModal(taxFreeType) {
            this.taxFreeType = taxFreeType;
            this.showCurrencyModal = !this.showCurrencyModal;
            if (this.showCurrencyModal) {
                this.createDataModal();
                return;
            }
            this.clearMenuOptions();
        },

        changeBaseItem(item) {
            if (!['userTax', 'companyTax'].includes(this.taxFreeType)) {
                return;
            }
            this.country[this.taxFreeType] = item;
        },

        createDataModal() {
            if (this.taxFreeType === 'companyTax' || this.taxFreeType === 'userTax') {
                this.currencyDependsValue = [this.country[this.taxFreeType]];
            }

            this.baseCurrencyId = this.currencyDependsValue[0].currencyId;

            if (Object.keys(this.admin_userConfigValues).length > 0) {
                this.pushDataFromUserConfig();
            }

            this.addCheckedHamburgerMenu();
            this.addDisabledBaseCurrencyCheckBox();
            this.sortCurrencyCheckBox();
        },

        clearMenuOptions() {
            this.menuOptions.forEach((checkBox) => {
                delete checkBox.checked;
                delete checkBox.disabled;
            });
        },

        addCheckedHamburgerMenu() {
            this.menuOptions = [...this.currencies];

            this.menuOptions.forEach((checkBox) => {
                const checked = this.currencyDependsValue.find((value) => {
                    return checkBox.id === value.currencyId;
                });

                checkBox.checked = !!checked;
            });
        },

        addDisabledBaseCurrencyCheckBox() {
            this.menuOptions.forEach((checkBox) => {
                checkBox.disabled = checkBox.id === this.baseCurrencyId;
            });
        },

        sortCurrencyCheckBox() {
            this.menuOptions.sort((a, b) => b.checked - a.checked || b.disabled - a.disabled);
        },

        pushDataFromUserConfig() {
            if (!this.admin_userConfigValues[this.taxFreeType]) {
                return;
            }
            this.admin_userConfigValues[this.taxFreeType].forEach((currencyId) => {
                const admin_userCurrencyDependsValue = {};
                admin_userCurrencyDependsValue.amount = this.calculateInheritedPrice(currencyId);
                admin_userCurrencyDependsValue.currencyId = currencyId;
                admin_userCurrencyDependsValue.enabled = false;
                const existedValue = this.currencyDependsValue.find((value) => {
                    return value.currencyId === currencyId;
                });

                if (!existedValue) {
                    this.currencyDependsValue.push(admin_userCurrencyDependsValue);
                }
            });
        },

        calculateInheritedPrice(currencyId) {
            const basePrice = this.currencyDependsValue.find((value) => {
                return value.enabled === true;
            });
            if (!basePrice) {
                return 0;
            }
            return this.getPriceByCurrency(basePrice, currencyId);
        },

        getPriceByCurrency(basePrice, currencyId) {
            const currency = this.getCurrencyById(currencyId);
            const currencyBaseItem = this.getCurrencyById(basePrice.currencyId);

            if (!currencyBaseItem.factor || !currency.factor) {
                return 0;
            }

            return (basePrice.amount / currencyBaseItem.factor) * currency.factor;
        },

        getCurrencyById(currencyId) {
            const currency = this.currencies.find((value) => {
                return value.id === currencyId;
            });

            return currency || {};
        },

        saveCountryCurrencyDependent() {
            this.$emit('modal-save');
        },
    },
};
