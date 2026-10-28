/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-country-create.html.twig';

const utils = Contena.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.country.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            Contena.Context.api.languageId = Contena.Context.api.systemLanguageId;

            if (this.$route.params.id) {
                this.country = this.countryRepository.create(Contena.Context.api, this.$route.params.id);
                this.country.customerTax = {
                    amount: 0,
                    currencyId: Contena.Context.app.systemCurrencyId,
                    enabled: false,
                };
                this.country.companyTax = {
                    amount: 0,
                    currencyId: Contena.Context.app.systemCurrencyId,
                    enabled: false,
                };
                this.countryId = this.country.id;
                this.countryStateRepository = this.repositoryFactory.create(
                    this.country.states.entity,
                    this.country.states.source,
                );
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.settings.country.detail',
                params: { id: this.country.id },
            });
        },
    },
};
