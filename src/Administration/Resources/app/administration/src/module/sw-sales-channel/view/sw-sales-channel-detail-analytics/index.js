/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-analytics.html.twig';

import './sw-sales-channel-detail-analytics.scss';

const { Context } = Contena;

/**
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        isLoading: {
            type: Boolean,
            default: false,
        },

        channel: {
            required: true,
        },
    },

    watch: {
        channel() {
            this.createAnalyticsData();
        },

        'channel.analytics.trackOrders'(newValue) {
            if (!newValue && this.channel?.analytics) {
                this.channel.analytics.enhancedConversions = false;
            }
        },
    },

    created() {
        this.createAnalyticsData();
    },

    methods: {
        createAnalyticsData() {
            if (this.channel && !this.channel.analytics) {
                const repository = this.repositoryFactory.create('channel_analytics');
                this.channel.analytics = repository.create(Context.api);
            }
        },
    },
};
