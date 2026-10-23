/**
 * @sw-package checkout
 */

import ApiService from 'src/core/service/api.service';
import template from './sw-user-imitate-user-modal.html.twig';
import './sw-user-imitate-user-modal.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'contextStoreService'],

    emits: ['modal-close'],

    mixins: [Mixin.getByName('notification')],

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            channelDomains: [],
            imitateCustomerTokens: {},
            isLoading: false,
        };
    },

    computed: {
        modalTitle() {
            return this.$t('sw-user.imitateCustomerModal.modalTitle', {
                firstname: this.user.firstName,
                lastname: this.user.lastName,
            });
        },

        modalDescription() {
            return this.$t('sw-user.imitateCustomerModal.modalDescription', {
                firstname: this.user.firstName,
                lastname: this.user.lastName,
            });
        },

        channelDomainRepository() {
            return this.repositoryFactory.create('channel_domain');
        },

        currentUser() {
            return Contena.Store.get('session').currentUser;
        },

        channelDomainCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('channel');
            criteria.addFilter(Criteria.equals('channel.typeId', Contena.Defaults.frontendSalesChannelTypeId));
            criteria.addFilter(Criteria.equals('channel.active', true));
            criteria.addSorting(Criteria.sort('channel.name', 'ASC'));
            criteria.addSorting(Criteria.sort('languageId', 'DESC'));

            if (this.user.boundSalesChannelId) {
                criteria.addFilter(Criteria.equals('channelId', this.user.boundSalesChannelId));
            }

            return criteria;
        },

        hasSalesChannelDomains() {
            return this.channelDomains !== null && this.channelDomains.length > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            try {
                await this.fetchSalesChannelDomains();
                await this.fetchImitateCustomerTokens();
            } finally {
                this.isLoading = false;
            }
        },

        onSalesChannelDomainMenuItemClick(channelId, channelDomainUrl) {
            const token = this.imitateCustomerTokens[channelId];

            if (!token) {
                this.createNotificationError({
                    message: this.$t('sw-user.detail.notificationImitateCustomerErrorMessage'),
                });

                return;
            }

            this.contextStoreService.redirectToSalesChannelUrl(
                channelDomainUrl,
                token,
                this.user.id,
                this.currentUser?.id,
            );
        },

        onCancel() {
            this.$emit('modal-close');
        },

        fetchSalesChannelDomains() {
            return this.channelDomainRepository
                .search(this.channelDomainCriteria, Contena.Context.api)
                .then((loadedDomains) => {
                    this.channelDomains = loadedDomains;
                });
        },

        fetchImitateCustomerTokens() {
            const channelIds = [...new Set(this.channelDomains.map((domain) => domain.channelId))];

            return Promise.all(
                channelIds.map((channelId) =>
                    this.contextStoreService
                        .generateImitateCustomerToken(this.user.id, channelId)
                        .then((response) => {
                            this.imitateCustomerTokens[channelId] = ApiService.handleResponse(response).token;
                        })
                        .catch(() => {}),
                ),
            );
        },
    },
};
