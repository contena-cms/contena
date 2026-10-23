/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-list.html.twig';
import './sw-sales-channel-list.scss';

const { Mixin, Defaults } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'domainLinkService'],

    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            channels: null,
            blogsForSalesChannel: {},
            isLoading: true,
            sortBy: 'name',
            searchConfigEntity: 'channel',
            lastSortedColumn: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        channelColumns() {
            const columns = [
                {
                    property: 'name',
                    dataIndex: 'name',
                    allowResize: true,
                    routerLink: 'sw.sales.channel.detail',
                    label: 'sw-sales-channel.list.columnName',
                    primary: true,
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    allowResize: true,
                    sortable: false,
                    label: 'sw-sales-channel.list.columnStatus',
                },
                {
                    property: 'id',
                    dataIndex: 'id',
                    allowResize: true,
                    sortable: false,
                    label: 'sw-sales-channel.list.columnFavourite',
                    align: 'center',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    allowResize: true,
                    label: 'sw-sales-channel.list.columnCreatedAt',
                },
            ];

            columns.splice(1, 0, {
                property: 'type.name',
                dataIndex: 'type.name',
                allowResize: true,
                label: 'sw-sales-channel.list.columnType',
            });

            return columns;
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        channelCriteria() {
            const channelCriteria = new Criteria(this.page, this.limit);

            channelCriteria.setTerm(this.term);
            channelCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            channelCriteria.addAssociation('type');
            channelCriteria.addAssociation('domains');

            return channelCriteria;
        },

        channelFavoritesService() {
            return Contena.Service('channelFavorites');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },
    },

    methods: {
        onAddSalesChannel() {
            Contena.Utils.EventBus.emit('sw-sales-channel-list-add-new-channel');
        },

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.channelCriteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            return this.channelRepository.search(criteria).then((searchResult) => {
                this.channels = searchResult;
                this.total = searchResult.total;
                this.isLoading = false;
            });
        },

        checkForDomainLink(channel) {
            const domainLink = this.domainLinkService.getDomainLink(channel);

            if (domainLink === null) {
                return false;
            }

            channel.domainLink = domainLink;

            return true;
        },

        openFrontendLink(storeFrontLink) {
            window.open(storeFrontLink, '_blank');
        },

        isFavorite(channelId) {
            return this.channelFavoritesService.isFavorite(channelId);
        },

        isFrontendSalesChannel(channel) {
            return channel.type.id === Defaults.frontendSalesChannelTypeId;
        },
    },
};
