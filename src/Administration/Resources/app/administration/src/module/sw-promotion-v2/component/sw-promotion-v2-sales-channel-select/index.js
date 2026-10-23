/**
 * @sw-package checkout
 */
import template from './sw-promotion-v2-sales-channel-select.html.twig';

const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            channels: [],
            sortBy: 'name',
        };
    },

    computed: {
        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        promotionSalesChannelRepository() {
            if (this.promotion) {
                return this.repositoryFactory.create(
                    this.promotion.channels.entity,
                    this.promotion.channels.source,
                );
            }

            return null;
        },

        channelIds: {
            get() {
                if (!this.promotion) {
                    return [];
                }

                return this.promotion.channels.map((promotionSalesChannels) => {
                    return promotionSalesChannels.channelId;
                });
            },

            set(channelsIds) {
                channelsIds = channelsIds || [];
                const { deleted, added } = this.getChangeset(channelsIds);

                if (this.promotion.isNew()) {
                    this.handleLocalMode(deleted, added);
                    return;
                }

                this.handleWithRepository(deleted, added);
            },
        },

        channelCriteria() {
            const channelCriteria = new Criteria(1, 500);
            channelCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            return channelCriteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.channelRepository.search(this.channelCriteria).then((searchresult) => {
                this.channels = searchresult;
            });
        },

        getChangeset(channelsIds) {
            const deleted = [];
            const added = [];

            channelsIds.forEach((id) => {
                const foundSalesChannel = this.promotion.channels.find((channel) => {
                    return channel.channelId === id;
                });

                if (!foundSalesChannel) {
                    added.push(id);
                }
            });

            this.promotion.channels.forEach((channel) => {
                if (!channelsIds.includes(channel.channelId)) {
                    deleted.push(channel.channelId);
                }
            });

            return { deleted, added };
        },

        getAssociationBySalesChannelId(channelId) {
            return this.promotion.channels.find((association) => {
                return association.channelId === channelId;
            });
        },

        handleLocalMode(deleted, added) {
            deleted.forEach((deletedId) => {
                const collectionEntry = this.getAssociationBySalesChannelId(deletedId);
                this.promotion.channels.remove(collectionEntry.id);
            });

            added.forEach((newId) => {
                const newAssociation = this.promotionSalesChannelRepository.create(this.promotion.channels.context);

                newAssociation.channelId = newId;
                newAssociation.promotionId = this.promotion.id;
                newAssociation.priority = 1;
                this.promotion.channels.add(newAssociation);
            });
        },

        handleWithRepository(deleted, added) {
            deleted.forEach((deletedId) => {
                const associationEntry = this.getAssociationBySalesChannelId(deletedId);
                this.promotion.channels.remove(associationEntry.id);
            });

            added.forEach((addedId) => {
                const newAssociation = this.promotionSalesChannelRepository.create(this.promotion.channels.context);

                newAssociation.channelId = addedId;
                newAssociation.promotionId = this.promotion.id;
                newAssociation.priority = 1;
                this.promotion.channels.add(newAssociation);
            });
        },
    },
};
