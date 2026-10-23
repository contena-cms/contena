import type { AxiosResponse } from 'axios';
import type {
    CalculatedPrice,
    Cart,
    CartError,
    ContextSwitchParameters,
    LineItem,
    PromotionCodeTag,
    ChannelContext,
} from '../order.types';

/**
 * @sw-package checkout
 */

const { Service } = Contena;

function filterEmptyLineItems(items: LineItem[]) {
    return items.filter((item) => item.label === '');
}

function reverseLineItems(items: LineItem[]) {
    return items.slice().reverse();
}

function mergeEmptyAndExistingLineItems(emptyLineItems: LineItem[], lineItems: LineItem[]) {
    // Reverse the lineItems so the newly added are at the top for better UX
    reverseLineItems(lineItems);

    return [...emptyLineItems, ...lineItems];
}

interface SwOrderState {
    cart: Cart;
    disabledAutoPromotion: boolean;
    sendOrderConfirmationMail: boolean;
    promotionCodes: PromotionCodeTag[];
    defaultSalesChannel: Entity<'channel'> | null;
    context: ChannelContext;
    user: Entity<'user'> | null;
}

const swOrderStore = Contena.Store.register({
    id: 'swOrder',

    state: (): SwOrderState => ({
        user: null,
        defaultSalesChannel: null,
        cart: {
            token: null,
            lineItems: [],
            price: {
                totalPrice: null,
            },
            deliveries: [],
        } as unknown as Cart,
        context: {
            token: '',
            user: null,
            paymentMethod: {
                translated: {
                    distinguishableName: '',
                },
            } as Entity<'payment_method'>,
            shippingMethod: {
                translated: {
                    name: '',
                },
            } as Entity<'shipping_method'>,
            currency: {
                isoCode: 'EUR',
                symbol: '€',
                totalRounding: {
                    decimals: 2,
                },
            } as Entity<'currency'>,
            channel: {
                id: '',
            } as Entity<'channel'>,
            context: {
                currencyId: '' as EntityKey<'currency'>,
                languageIdChain: [],
            },
        },
        promotionCodes: [],
        disabledAutoPromotion: false,
        sendOrderConfirmationMail: true,
    }),

    getters: {
        isCustomerActive(state: SwOrderState): boolean {
            return !!state?.context.user?.active;
        },

        isCartTokenAvailable(state: SwOrderState): boolean {
            return !!state?.cart?.token;
        },

        currencyId(state: SwOrderState): string {
            return state?.context.context.currencyId ?? '';
        },

        invalidPromotionCodes(state: SwOrderState): PromotionCodeTag[] {
            return state.promotionCodes.filter((item) => item.isInvalid);
        },

        cartErrors(state: SwOrderState): CartError[] {
            return state?.cart?.errors ?? null;
        },
    },

    actions: {
        setCustomer(user: Entity<'user'> | null) {
            this.context.user = user;
            this.user = user;
        },

        setDefaultSalesChannel(channel: Entity<'channel'> | null) {
            this.defaultSalesChannel = channel;
        },

        setCartToken(token: string) {
            this.cart.token = token;
        },

        setCart(cart: Cart) {
            const emptyLineItems = filterEmptyLineItems(this.cart.lineItems);
            this.cart = cart;
            this.cart.lineItems = mergeEmptyAndExistingLineItems(emptyLineItems, this.cart.lineItems);
        },

        setCartLineItems(lineItems: LineItem[]) {
            this.cart.lineItems = lineItems;
        },

        setCurrency(currency: Entity<'currency'>) {
            this.context.currency = currency;
        },

        setContext(context: ChannelContext) {
            this.context = context;
        },

        setPromotionCodes(promotionCodes: PromotionCodeTag[]) {
            this.promotionCodes = promotionCodes;
        },

        removeEmptyLineItem(emptyLineItemKey: string) {
            this.cart.lineItems = this.cart.lineItems.filter((item) => item.id !== emptyLineItemKey);
        },

        removeInvalidPromotionCodes() {
            this.promotionCodes = this.promotionCodes.filter((item) => !item.isInvalid);
        },

        setDisabledAutoPromotion(disabledAutoPromotion: boolean) {
            this.disabledAutoPromotion = disabledAutoPromotion;
        },

        setSendOrderConfirmationMail(sendOrderConfirmationMail: boolean) {
            this.sendOrderConfirmationMail = sendOrderConfirmationMail;
        },

        selectExistingCustomer({ user }: { user: Entity<'user'> | null }) {
            this.setCustomer(user);
            this.setDefaultSalesChannel(user?.channel ?? null);
        },

        createCart({ channelId }: { channelId: EntityKey<'channel'> }) {
            return Service('cartStoreService')
                .createCart(channelId)
                .then((response: AxiosResponse): string => {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    const token = response.data.token as string;
                    this.setCartToken(token);
                    return token;
                })
                .then((contextToken) => {
                    return Service('contextStoreService')
                        .getSalesChannelContext(channelId, contextToken)
                        .then((response: AxiosResponse) => this.setContext(response.data as ChannelContext));
                });
        },

        getCart({ channelId, contextToken }: { channelId: EntityKey<'channel'>; contextToken: string }) {
            if (`${contextToken}`.length !== 32) {
                throw new Error('Invalid context token');
            }

            return Promise.all([
                Service('cartStoreService')
                    .getCart(channelId, contextToken)
                    .then((response: AxiosResponse) => this.setCart(response.data as Cart)),
                Service('contextStoreService')
                    .getSalesChannelContext(channelId, contextToken)
                    .then((response: AxiosResponse) => this.setContext(response.data as ChannelContext)),
            ]);
        },

        cancelCart({ channelId, contextToken }: { channelId: EntityKey<'channel'>; contextToken: string }) {
            if (`${contextToken}`.length !== 32) {
                throw new Error('Invalid context token');
            }

            return Service('cartStoreService')
                .cancelCart(channelId, contextToken)
                .then(() => this.$reset());
        },

        updateCustomerContext({
            userId,
            channelId,
            contextToken,
        }: {
            userId: EntityKey<'user'>;
            channelId: EntityKey<'channel'>;
            contextToken: string;
        }) {
            return Service('contextStoreService').updateCustomerContext(userId, channelId, contextToken);
        },

        updateOrderContext({
            context,
            channelId,
            contextToken,
        }: {
            context: ContextSwitchParameters;
            channelId: EntityKey<'channel'>;
            contextToken: string;
        }) {
            return Service('contextStoreService').updateContext(context, channelId, contextToken);
        },

        getContext({ channelId, contextToken }: { channelId: EntityKey<'channel'>; contextToken: string }) {
            return Service('contextStoreService').getSalesChannelContext(channelId, contextToken);
        },

        saveOrder({
            channelId,
            contextToken,
            sendMail,
        }: {
            channelId: EntityKey<'channel'>;
            contextToken: string;
            sendMail?: boolean;
        }) {
            return Service('checkoutStoreService').checkout(
                channelId,
                contextToken,
                {},
                {},
                {
                    sendOrderConfirmationMail: sendMail ?? this.sendOrderConfirmationMail,
                },
            );
        },

        removeLineItems({
            channelId,
            contextToken,
            lineItemKeys,
        }: {
            channelId: EntityKey<'channel'>;
            contextToken: string;
            lineItemKeys: string[];
        }) {
            return Service('cartStoreService')
                .removeLineItems(channelId, contextToken, lineItemKeys)
                .then((response: AxiosResponse) => this.setCart(response.data as Cart));
        },

        saveLineItem({
            channelId,
            contextToken,
            item,
        }: {
            channelId: EntityKey<'channel'>;
            contextToken: string;
            item: LineItem;
        }) {
            return Service('cartStoreService')
                .saveLineItem(channelId, contextToken, item)
                .then((response: AxiosResponse) => this.setCart(response.data as Cart));
        },

        saveMultipleLineItems({
            channelId,
            contextToken,
            items,
        }: {
            channelId: EntityKey<'channel'>;
            contextToken: string;
            items: LineItem[];
        }) {
            return Service('cartStoreService')
                .addMultipleLineItems(channelId, contextToken, items)
                .then((response: AxiosResponse) => this.setCart(response.data as Cart));
        },

        addPromotionCode({
            channelId,
            contextToken,
            code,
        }: {
            channelId: EntityKey<'channel'>;
            contextToken: string;
            code: string;
        }): Promise<void> {
            return Service('cartStoreService')
                .addPromotionCode(channelId, contextToken, code)
                .then((response) => this.setCart(response.data as Cart));
        },

        modifyShippingCosts({
            channelId,
            contextToken,
            shippingCosts,
        }: {
            channelId: EntityKey<'channel'>;
            contextToken: string;
            shippingCosts: CalculatedPrice;
        }) {
            return (
                Service('cartStoreService')
                    ?.modifyShippingCosts(channelId, contextToken, shippingCosts)
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                    .then((response: AxiosResponse) => this.setCart(response.data.data as Cart))
            );
        },

        remindPayment({ orderTransactionId }: { orderTransactionId: EntityKey<'order_transaction'> }) {
            return Service('orderStateMachineService').transitionOrderTransactionState(orderTransactionId, 'remind');
        },
    },
});

/**
 * @private
 */
export default swOrderStore;

/**
 * @private
 */
export type SwOrderStore = ReturnType<typeof swOrderStore>;

/**
 * @private
 */
export type { SwOrderState };
