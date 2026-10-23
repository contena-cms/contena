/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_CHANNEL
 */

const { Criteria } = Contena.Data;

Contena.Mixin.register('export-channel-filter', {
    inject: ['repositoryFactory', 'filterFactory'],

    data() {
        return {
            exportChannelOptions: [],
        };
    },

    computed: {
        channelRepository() {
            return this.repositoryFactory.create('channel');
        },
    },

    methods: {
        loadExportChannelOptions() {
            const criteria = new Criteria(1, 500);

            criteria.addFilter(Criteria.equals('typeId', Contena.Defaults.agenticCommerceTypeId));
            criteria.addSorting(Criteria.sort('name'));

            this.channelRepository.search(criteria).then((result) => {
                this.exportChannelOptions = result;
            });
        },

        insertExportChannelFilter(filters, entity, labelKey, placeholderKey) {
            const exportChannelFilter = this.filterFactory
                .create(entity, {
                    'export-channel-filter': {
                        property: 'channelTracking.channelId',
                        type: 'multi-select-filter',
                        label: this.$t(labelKey),
                        placeholder: this.$t(placeholderKey),
                        valueProperty: 'id',
                        labelProperty: 'name',
                        options: this.exportChannelOptions,
                    },
                })
                .pop();

            const anchorIndex = filters.findIndex((f) => f.name === 'campaign-code-filter');
            if (anchorIndex !== -1) {
                filters.splice(anchorIndex + 1, 0, exportChannelFilter);
            } else {
                filters.push(exportChannelFilter);
            }

            return filters;
        },
    },
});
