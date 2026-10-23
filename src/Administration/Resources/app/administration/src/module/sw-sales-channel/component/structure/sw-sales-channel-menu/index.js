/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-menu.html.twig';
import './sw-sales-channel-menu.scss';

const { Criteria } = Contena.Data;
const FlatTree = Contena.Helper.FlatTreeHelper;

/**
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'domainLinkService'],

    data() {
        return {
            channels: [],
            channelsLoaded: false,
            showModal: false,
            isLoading: true,
            isMobileViewport: false,
            contextMenuOpen: false,
        };
    },

    computed: {
        adminMenuStore() {
            return Contena.Store.get('adminMenu');
        },

        isSidebarExpanded() {
            // The off-canvas panel always shows the expanded layout
            return this.adminMenuStore.isExpanded || this.isMobileViewport;
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        channelModuleColor() {
            // The rows are built here instead of from the module navigation, so they pick up the
            // module color themselves and follow the module icon color preference like the module rows
            return Contena.Module.getModuleByEntityName('channel')?.manifest?.color;
        },

        canCreateSalesChannels() {
            return this.acl.can('channel.creator');
        },

        // Gated on the finished request, so the row never flashes while loading.
        // Stale favourites of deleted channels return zero rows although channels exist.
        showAddChannelMenuItem() {
            return (
                this.channelsLoaded &&
                this.channels.length === 0 &&
                this.channelFavorites.length === 0 &&
                this.canCreateSalesChannels
            );
        },

        channelCriteria() {
            const criteria = new Criteria(1, 7);

            criteria.addIncludes({
                channel: [
                    'name',
                    'type',
                    'active',
                    'translated',
                    'domains',
                ],
                channel_type: ['iconName'],
                channel_domain: ['url', 'languageId'],
            });

            criteria.addSorting(Criteria.sort('channel.name', 'ASC'));
            criteria.addAssociation('type');
            criteria.addAssociation('domains');

            if (this.channelFavorites.length) {
                criteria.setLimit(50);
                criteria.addFilter(Criteria.equalsAny('id', this.channelFavorites));
            }

            return criteria;
        },

        moreSalesChannelAvailable() {
            return this.channels?.total > this.channels?.length;
        },

        buildMenuTree() {
            const flatTree = new FlatTree();

            this.channels.forEach((channel) => {
                flatTree.add({
                    id: channel.id,
                    path: 'sw.sales.channel.detail',
                    params: { id: channel.id },
                    label: {
                        label: channel.translated.name,
                        translated: true,
                    },
                    icon: channel.type.iconName,
                    color: this.channelModuleColor,
                    children: [],
                    domainLink: this.getDomainLink(channel),
                    active: channel.active,
                });
            });

            return flatTree.convertToTree();
        },

        moreItemsEntry() {
            return {
                children: [],
                icon: 'regular-eye',
                color: this.channelModuleColor,
                label: this.$t('sw-sales-channel.general.titleMenuMoreItems'),
                path: 'sw.sales.channel.list',
            };
        },

        channelFavoritesService() {
            return Contena.Service('channelFavorites');
        },

        channelFavorites() {
            if (this.isLoading) {
                return [];
            }

            return this.channelFavoritesService.getFavoriteIds();
        },
    },

    watch: {
        channelFavorites() {
            if (this.isLoading) {
                return;
            }

            this.loadEntityData();
        },

        // The teleported action menu would keep floating over the next page otherwise
        '$route.path'() {
            this.contextMenuOpen = false;
        },

        // The teleported action menu would float detached over the hidden off-canvas rail otherwise
        isMobileViewport(isMobile) {
            if (isMobile) {
                this.contextMenuOpen = false;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.mobileViewportQuery = this.$device.getMediaQuery('(max-width: 1280px)');
            this.mobileViewportQuery.addEventListener('change', this.syncMobileViewport);
            this.syncMobileViewport();

            this.registerListener();

            this.channelFavoritesService.initService().finally(() => {
                this.isLoading = false;
            });
        },

        syncMobileViewport() {
            this.isMobileViewport = this.mobileViewportQuery.matches;
        },

        registerListener() {
            Contena.Utils.EventBus.on('sw-sales-channel-detail-sales-channel-change', this.loadEntityData);
            Contena.Utils.EventBus.on('sw-language-switch-change-application-language', this.loadEntityData);
            Contena.Utils.EventBus.on('sw-sales-channel-detail-base-sales-channel-change', this.loadEntityData);
            Contena.Utils.EventBus.on('sw-sales-channel-list-add-new-channel', this.openSalesChannelModal);
        },

        destroyedComponent() {
            this.mobileViewportQuery?.removeEventListener('change', this.syncMobileViewport);
            Contena.Utils.EventBus.off('sw-sales-channel-detail-sales-channel-change', this.loadEntityData);
            Contena.Utils.EventBus.off('sw-language-switch-change-application-language', this.loadEntityData);
            Contena.Utils.EventBus.off('sw-sales-channel-detail-base-sales-channel-change', this.loadEntityData);
            Contena.Utils.EventBus.off('sw-sales-channel-list-add-new-channel', this.openSalesChannelModal);
        },

        getDomainLink(channel) {
            return this.domainLinkService.getDomainLink(channel);
        },

        loadEntityData() {
            return this.channelRepository.search(this.channelCriteria).then((response) => {
                this.channels = response;
                this.channelsLoaded = true;
            });
        },

        openSalesChannelModal() {
            this.showModal = true;
        },

        openFrontendLink(storeFrontLink) {
            window.open(storeFrontLink, '_blank');
        },
    },
};
