import template from './sw-settings-listing-default-sales-channel.html.twig';
import './sw-settings-listing-default-sales-channel.scss';

/**
 * @sw-package inventory
 */

const { EntityCollection } = Contena.Data;
const { isEmpty } = Contena.Utils.types;
const { cloneDeep } = Contena.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default() {
                return false;
            },
        },
    },

    data() {
        return {
            isDefaultSalesChannelLoading: false,
            displayVisibilityDetail: false,
            configData: {
                null: {
                    'core.defaultSalesChannel.active': true,
                    'core.defaultSalesChannel.channel': [],
                    'core.defaultSalesChannel.visibility': {},
                },
            },
            visibilityConfig: [],
        };
    },

    computed: {
        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        channel: {
            get() {
                return this.configData?.null?.['core.defaultSalesChannel.channel'];
            },
            set(channel) {
                this.configData.null['core.defaultSalesChannel.channel'] = channel;
            },
        },
    },

    watch: {
        channel: {
            handler() {
                if (!this.channel.length) {
                    this.visibilityConfig = [];
                    return;
                }

                const channelIds = this.channel.map((channel) => channel.id);
                this.visibilityConfig = this.visibilityConfig.filter((entry) => channelIds.includes(entry.id));

                const configData = new Map();
                this.visibilityConfig.forEach((entry) => configData.set(entry.id, { ...entry }));

                this.channel.forEach((channel) => {
                    configData.set(channel, {
                        id: channel,
                        visibility: this.configData.null?.['core.defaultSalesChannel.visibility'][channel] || 30,
                    });
                });

                this.visibilityConfig = [...configData.values()];
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSalesChannelsSystemConfig();
        },

        fetchSalesChannelsSystemConfig() {
            this.isDefaultSalesChannelLoading = true;

            const channelEntity = new EntityCollection(
                this.channelRepository.route,
                this.channelRepository.entityName,
                Contena.Context.api,
            );

            this.systemConfigApiService.getValues('core.defaultSalesChannel').then((configData) => {
                this.isDefaultSalesChannelLoading = false;

                if (!isEmpty(configData)) {
                    this.configData.null = configData;
                    this.channel.forEach((channel) => channelEntity.add(channel));
                    this.channel = channelEntity;

                    return;
                }

                this.channel = channelEntity;
            });
        },

        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;

            this.visibilityConfig = cloneDeep(this.$refs.visibilityConfig.items);

            this.configData.null['core.defaultSalesChannel.visibility'] = {};
            this.visibilityConfig.forEach((entry) => {
                this.configData.null['core.defaultSalesChannel.visibility'][entry.id] = entry.visibility;
            });
        },

        saveSalesChannelVisibilityConfig() {
            return this.systemConfigApiService.batchSave(this.configData);
        },

        updateSalesChannel(channel) {
            this.channel = channel;
        },
    },
};
