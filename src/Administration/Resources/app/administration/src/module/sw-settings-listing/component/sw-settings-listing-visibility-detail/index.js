import template from './sw-settings-listing-visibility-detail.html.twig';

/**
 * @sw-package inventory
 */

const { Criteria } = Contena.Data;
const { Filter } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        config: {
            required: true,
            type: Array,
        },
    },

    data() {
        return {
            items: [],
            page: 1,
            limit: 10,
            total: 0,
        };
    },

    computed: {
        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        truncateFilter() {
            return Filter.getByName('truncate');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onPageChange({ page: this.page, limit: this.limit });
        },

        onPageChange(params) {
            const offset = (params.page - 1) * params.limit;
            this.total = this.config.length;

            this.fetchSalesChannels().then((config) => {
                this.items = config.slice(offset, offset + params.limit);
            });
        },

        changeVisibilityValue(event, item) {
            item.visibility = Number(event);
        },

        fetchSalesChannels() {
            const channelIds = this.config.map((config) => config.id);
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('id', channelIds));

            return this.channelRepository.search(criteria).then((channels) => {
                return this.config.map((config) => {
                    const channel = channels.get(config.id);
                    if (!channel) {
                        return config;
                    }

                    return {
                        ...config,
                        name: channel.name,
                    };
                });
            });
        },
    },
};
