/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-modal.html.twig';
import './sw-sales-channel-modal.scss';

const { Defaults } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['modal-close'],

    data() {
        return {
            detailType: null,
            contentStreamsExist: false,
            contentStreamsLoading: false,
        };
    },

    computed: {
        modalTitle() {
            if (this.detailType) {
                return this.$t(
                    'sw-sales-channel.modal.titleDetailPrefix',
                    {
                        name: this.detailType.name,
                    },
                    0,
                );
            }

            return this.$t('sw-sales-channel.modal.title');
        },

        contentStreamRepository() {
            return this.repositoryFactory.create('content_stream');
        },

        addChannelAction() {
            return {
                loading: (channelTypeId) => {
                    return this.isProductComparisonSalesChannelType(channelTypeId) && this.contentStreamsLoading;
                },

                disabled: (channelTypeId) => {
                    return this.isProductComparisonSalesChannelType(channelTypeId) && !this.contentStreamsExist;
                },
            };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.contentStreamsLoading = true;
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('internal', false));
            this.contentStreamRepository.search(criteria).then((result) => {
                if (result.total > 0) {
                    this.contentStreamsExist = true;
                }
                this.contentStreamsLoading = false;
            });
        },

        onGridOpenDetails(detailType) {
            this.detailType = detailType;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddChannel(id) {
            this.onCloseModal();

            if (id) {
                this.$router.push({
                    name: 'sw.sales.channel.create',
                    params: { typeId: id },
                });
            }
        },

        openRoute(route) {
            this.onCloseModal();

            this.$router.push(route);
        },

        isProductComparisonSalesChannelType(channelTypeId) {
            return (
                channelTypeId === Defaults.blogComparisonTypeId ||
                channelTypeId === Defaults.agenticCommerceTypeId
            );
        },
    },
};
