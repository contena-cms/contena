/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-modal-grid.html.twig';
import './sw-sales-channel-modal-grid.scss';

const { Defaults } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['grid-channel-add', 'grid-detail-open'],

    props: {
        blogStreamsExist: {
            type: Boolean,
            required: false,
            default: true,
        },

        blogStreamsLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        addChannelAction: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            channelTypes: [],
            isLoading: false,
            total: 0,
        };
    },

    computed: {
        channelTypeRepository() {
            return this.repositoryFactory.create('channel_type');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            const context = {
                ...Contena.Context.api,
                languageId: Contena.Store.get('session').languageId,
            };
            const criteria = new Criteria(1, 500);

            this.channelTypeRepository.search(criteria, context).then((response) => {
                this.total = response.total;
                this.channelTypes = response;
                this.isLoading = false;
            });
        },

        onAddChannel(id) {
            this.$emit('grid-channel-add', id);
        },

        onOpenDetail(id) {
            const detailType = this.channelTypes.find((channelType) => channelType.id === id);
            this.$emit('grid-detail-open', detailType);
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        isAgenticCommerceSalesChannelType(channelTypeId) {
            return channelTypeId === Defaults.agenticCommerceTypeId;
        },

        isProductComparisonSalesChannelType(channelTypeId) {
            return channelTypeId === Defaults.blogComparisonTypeId;
        },

        getTooltip(item) {
            const isDisabledAgenticCommerceType =
                !this.showAgenticCommerceType() && this.isAgenticCommerceSalesChannelType(item.id);
            const messageKey = isDisabledAgenticCommerceType
                ? 'sw-sales-channel.modal.messageAgenticCommerce'
                : 'sw-sales-channel.modal.messageNoProductStreams';

            return {
                message: this.$t(messageKey),
                showOnDisabledElements: true,
                disabled: !this.isDisabled(item),
            };
        },

        isDisabled(item) {
            return (
                this.addChannelAction.disabled(item.id) ||
                (!this.showAgenticCommerceType() && this.isAgenticCommerceSalesChannelType(item.id))
            );
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        showAgenticCommerceType() {
            return !!Contena.Context.app.config.bundles?.SwagAgenticCommerce;
        },
    },
};
