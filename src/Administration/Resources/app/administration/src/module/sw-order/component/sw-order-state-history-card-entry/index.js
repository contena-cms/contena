import './sw-order-state-history-card-entry.scss';
import template from './sw-order-state-history-card-entry.html.twig';

/**
 * @sw-package checkout
 *
 * @deprecated tag:v6.8.0 - will be removed, no usages found
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['stateStyleDataProviderService'],

    props: {
        history: {
            type: Array,
            required: true,
        },
        transitionOptions: {
            type: Array,
            required: true,
        },
        stateMachineName: {
            type: String,
            required: true,
        },
        title: {
            type: String,
            required: false,
            default: '',
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },
    },

    methods: {
        admin_userDisplayName(admin_user) {
            return `${this.$t('sw-order.stateCard.labelLastEditedBy')} ${admin_user.username}`;
        },

        integrationDisplayName(integration) {
            return this.$t('sw-order.stateCard.labelLastEditedByIntegration', { integrationName: integration.label });
        },

        getDisplayName(historyEntry) {
            if (historyEntry.admin_user !== null) {
                return this.admin_userDisplayName(historyEntry.admin_user);
            }
            if (historyEntry.integration !== null) {
                return this.integrationDisplayName(historyEntry.integration);
            }
            return this.$t('sw-order.stateCard.labelSystemUser');
        },

        getIconFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).icon;
        },

        getIconColorFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).iconStyle;
        },

        getBackgroundColorFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).iconBackgroundStyle;
        },
    },
};
