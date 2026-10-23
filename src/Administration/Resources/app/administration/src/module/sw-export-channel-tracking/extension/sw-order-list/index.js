/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_CHANNEL
 */

const { Component } = Contena;

Component.override('sw-order-list', {
    mixins: [Contena.Mixin.getByName('export-channel-filter')],

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');

            criteria.addAssociation('channelTracking.channel');

            return criteria;
        },

        listFilters() {
            const filters = this.$super('listFilters');

            return this.insertExportChannelFilter(
                filters,
                'order',
                'sw-export-channel-tracking.extension.sw-order-list.filters.exportFeedFilter.label',
                'sw-export-channel-tracking.extension.sw-order-list.filters.exportFeedFilter.placeholder',
            );
        },
    },

    methods: {
        createdComponent() {
            this.defaultFilters.push('export-channel-filter');
            this.loadExportChannelOptions();

            return this.$super('createdComponent');
        },

        getOrderColumns() {
            const columns = this.$super('getOrderColumns');

            columns.push({
                property: 'extensions.channelTracking.channel.name',
                dataIndex: 'extensions.channelTracking.channelId',
                label: 'sw-export-channel-tracking.extension.sw-order-list.list.columnExportFeed',
                allowResize: true,
                visible: false,
            });

            return columns;
        },
    },
});
