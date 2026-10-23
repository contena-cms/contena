import template from './sw-order-create-initial-modal.html.twig';
import './sw-order-create-initial-modal.scss';

import type { TabItem } from '@contena-ag/meteor-component-library/dist/esm/MtTabs';
import type { Cart, LineItem, ChannelContext, ContextSwitchParameters, CartDelivery } from '../../order.types';

import { LineItemType } from '../../order.types';

const { Component, Store, Mixin, Service } = Contena;

interface PromotionCodeItem {
    type: string;
    referencedId: string;
}

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['feature'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('cart-notification')],

    data(): {
        isLoading: boolean;
        isProductGridLoading: boolean;
        disabledAutoPromotion: boolean;
        sendOrderConfirmationMail: boolean;
        promotionCodes: string[];
        blogItems: LineItem[];
        context: ContextSwitchParameters;
        shippingCosts: number | null;
        activeTab: string;
    } {
        return {
            blogItems: [],
            promotionCodes: [],
            isLoading: false,
            isProductGridLoading: false,
            disabledAutoPromotion: false,
            sendOrderConfirmationMail: true,
            shippingCosts: null,
            activeTab: 'user',
            context: {
                currencyId: '' as EntityKey<'currency'>,
                paymentMethodId: '' as EntityKey<'payment_method'>,
                shippingMethodId: '' as EntityKey<'shipping_method'>,
                languageId: '' as EntityKey<'language'>,
                billingAddressId: '' as EntityKey<'user_address'>,
                shippingAddressId: '' as EntityKey<'user_address'>,
            },
        };
    },

    computed: {
        channelId(): EntityKey<'channel'> {
            return this.user?.channelId ?? ('' as EntityKey<'channel'>);
        },

        channelContext(): ChannelContext {
            return Store.get('swOrder').context;
        },

        currency(): Entity<'currency'> {
            return this.channelContext.currency;
        },

        cart(): Cart {
            return Store.get('swOrder').cart;
        },

        user(): Entity<'user'> | null {
            return Store.get('swOrder').user;
        },

        isCustomerActive(): boolean {
            return Store.get('swOrder').isCustomerActive;
        },

        promotionCodeItems(): PromotionCodeItem[] {
            return this.promotionCodes.map((code) => {
                return {
                    type: LineItemType.PROMOTION,
                    referencedId: code,
                };
            });
        },

        cartDelivery(): CartDelivery | null {
            return this.cart?.deliveries[0] as CartDelivery | null;
        },

        orderCreateInitialModalTabs(): TabItem[] {
            return [
                {
                    label: this.$t('sw-order.initialModal.tabCustomer'),
                    name: 'user',
                },
                {
                    label: this.$t('sw-order.initialModal.tabProducts'),
                    name: 'blogs',
                    disabled: !this.user || undefined,
                },
                {
                    label: this.$t('sw-order.initialModal.tabOptions'),
                    name: 'options',
                    disabled: !this.user || undefined,
                },
            ];
        },
    },

    watch: {
        channelContext(value: ChannelContext): void {
            // Update context after switching user successfully
            this.context = {
                ...this.context,
                currencyId: value.context.currencyId,
                languageId: value.context.languageIdChain[0],
                shippingMethodId: value.shippingMethod.id,
                paymentMethodId: value.paymentMethod.id,
                billingAddressId: value.user?.activeBillingAddress?.id ?? ('' as EntityKey<'user_address'>),
                shippingAddressId: value.user?.activeShippingAddress?.id ?? ('' as EntityKey<'user_address'>),
            };
        },
    },

    methods: {
        onCloseModal(): void {
            if (!this.user || !this.cart.token) {
                this.$emit('modal-close');
                return;
            }

            void this.cancelCart().then(() => {
                this.$emit('modal-close');
            });
        },

        async onPreviewOrder(): Promise<void> {
            const promises = [];

            this.isLoading = true;
            Store.get('swOrder').setSendOrderConfirmationMail(this.sendOrderConfirmationMail);

            promises.push(this.updateOrderContext());

            if (this.disabledAutoPromotion) {
                promises.push(this.disableAutoAppliedPromotions());
            }

            if (this.promotionCodes.length) {
                promises.push(this.addPromotionCodes());
            }

            if (this.shippingCosts !== null && this.shippingCosts !== this.cartDelivery?.shippingCosts?.totalPrice) {
                promises.push(this.modifyShippingCost(this.shippingCosts));
            }

            try {
                const responses = await Promise.all(promises);
                if (responses) {
                    this.$emit('order-preview');
                }
            } finally {
                this.isLoading = false;
            }
        },

        async onSaveItem(item: LineItem): Promise<void> {
            this.isProductGridLoading = true;

            try {
                await Store.get('swOrder').saveLineItem({
                    channelId: this.channelId,
                    contextToken: this.cart.token,
                    item,
                });
            } finally {
                this.isProductGridLoading = false;
            }
        },

        async addPromotionCodes(): Promise<void> {
            if (!this.user) return;

            await Store.get('swOrder').saveMultipleLineItems({
                channelId: this.user?.channelId,
                contextToken: this.cart.token,
                items: this.promotionCodeItems as unknown as LineItem[],
            });
        },

        updatePromotion(promotions: string[]): void {
            this.promotionCodes = promotions;
        },

        async onRemoveItems(lineItemKeys: string[]): Promise<void> {
            this.isProductGridLoading = true;

            try {
                await Store.get('swOrder').removeLineItems({
                    channelId: this.channelId,
                    contextToken: this.cart.token,
                    lineItemKeys: lineItemKeys,
                });
            } finally {
                this.isProductGridLoading = false;
            }
        },

        updateAutoPromotionToggle(value: boolean): void {
            this.disabledAutoPromotion = value;
        },

        updateSendOrderConfirmationMail(value: boolean): void {
            this.sendOrderConfirmationMail = value;
        },

        updateShippingCost(value: number): void {
            this.shippingCosts = value;
        },

        async updateOrderContext(): Promise<void> {
            await Store.get('swOrder').updateOrderContext({
                context: this.context,
                channelId: this.channelId,
                contextToken: this.cart.token,
            });
        },

        disableAutoAppliedPromotions(): Promise<void> {
            const additionalParams = { channelId: this.channelId };

            return Service('cartStoreService')
                .disableAutomaticPromotions(this.cart.token, additionalParams)
                .then(() => {
                    Store.get('swOrder').setDisabledAutoPromotion(true);
                });
        },

        async modifyShippingCost(amount: number): Promise<void> {
            if (!this.cartDelivery) {
                return;
            }

            const positiveAmount = Math.abs(amount);
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;

            if (!this.user) return;

            await Store.get('swOrder').modifyShippingCosts({
                channelId: this.user?.channelId,
                contextToken: this.cart.token,
                shippingCosts: this.cartDelivery?.shippingCosts,
            });
        },

        cancelCart(): Promise<void> {
            return Store.get('swOrder').cancelCart({
                channelId: this.channelId,
                contextToken: this.cart.token,
            });
        },
    },
});
