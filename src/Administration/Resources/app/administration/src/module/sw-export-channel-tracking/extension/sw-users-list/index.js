/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_CHANNEL
 */

const { Component } = Contena;

Component.override('sw-user-list', {
    mixins: [Contena.Mixin.getByName('export-channel-filter')],

    computed: {
        defaultCriteria() {
            const criteria = this.$super('defaultCriteria');

            criteria.addAssociation('channelTracking.channel');

            return criteria;
        },

        listFilters() {
            const filters = this.$super('listFilters');

            return this.insertExportChannelFilter(
                filters,
                'user',
                'sw-export-channel-tracking.extension.sw-user-list.filters.exportFeedFilter.label',
                'sw-export-channel-tracking.extension.sw-user-list.filters.exportFeedFilter.placeholder',
            );
        },
    },

    methods: {
        createdComponent() {
            this.defaultFilters.push('export-channel-filter');
            this.loadExportChannelOptions();

            return this.$super('createdComponent');
        },

        getCustomerColumns() {
            const columns = this.$super('getCustomerColumns');

            columns.push({
                property: 'extensions.channelTracking.channel.name',
                dataIndex: 'extensions.channelTracking.channelId',
                label: 'sw-export-channel-tracking.extension.sw-user-list.list.columnExportFeed',
                allowResize: true,
                visible: false,
            });

            return columns;
        },
    },
});
