import './sw-order-create-general-info.scss';
import template from './sw-order-create-general-info.html.twig';
import type { Cart, ChannelContext } from '../../order.types';

/**
 * @sw-package checkout
 */

const { Component, Mixin } = Contena;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        cart: {
            type: Object as PropType<Cart>,
            required: true,
        },
        context: {
            type: Object as PropType<ChannelContext>,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        summaryMainHeader(): string {
            if (!this.context.user) {
                return '';
            }

            return `${this.context.user.firstName} ${this.context.user.lastName} (${this.context.user.email})`;
        },

        paymentMethodName(): string {
            return this.context.paymentMethod?.translated?.distinguishableName ?? '';
        },

        shippingMethodName(): string {
            return this.context.shippingMethod.translated?.name ?? '';
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },
    },
});
