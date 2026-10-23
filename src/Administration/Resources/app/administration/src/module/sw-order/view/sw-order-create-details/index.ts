import template from './sw-order-create-details.html.twig';
import type {
    Cart,
    LineItem,
    ChannelContext,
    PromotionCodeTag,
    ContextSwitchParameters,
    CartDelivery,
} from '../../order.types';
import type CriteriaType from '../../../../core/data/criteria.data';
import { LineItemType } from '../../order.types';
import type Repository from '../../../../core/data/repository.data';
import { get } from '../../../../core/service/utils/object.utils';

/**
 * @sw-package checkout
 */

const { Component, Mixin, Store } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'cartStoreService'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('cart-notification')],

    data(): {
        isLoading: boolean;
        showPromotionModal: boolean;
        promotionError: ContenaHttpError | null;
        context: ContextSwitchParameters;
    } {
        return {
            showPromotionModal: false,
            promotionError: null,
            isLoading: false,
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
            return this.channelContext?.channel.id || ('' as EntityKey<'channel'>);
        },

        user(): Entity<'user'> | null {
            return Store.get('swOrder').user;
        },

        cart(): Cart {
            return Store.get('swOrder').cart;
        },

        currency(): Entity<'currency'> {
            return Store.get('swOrder').context.currency;
        },

        channelContext(): ChannelContext {
            return Store.get('swOrder').context;
        },

        email(): string {
            return this.user?.email || '';
        },

        phoneNumber(): string {
            return this.user?.defaultBillingAddress?.phoneNumber || '';
        },

        cartDelivery(): CartDelivery | null {
            return get(this.cart, 'deliveries[0]', null);
        },

        shippingCosts: {
            get(): number {
                return this.cartDelivery?.shippingCosts.totalPrice || 0.0;
            },
            set(value: number): void {
                this.modifyShippingCosts(value);
            },
        },

        deliveryDate(): string {
            return this.cartDelivery?.deliveryDate.earliest || '';
        },

        shippingMethodCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('channels.id', this.channelId));

            return criteria;
        },

        paymentMethodCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('channels.id', this.channelId));

            criteria.addFilter(Criteria.equals('active', 1));

            return criteria;
        },

        languageCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('channels.id', this.channelId));

            return criteria;
        },

        currencyCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('channels.id', this.channelId));

            return criteria;
        },

        currencyRepository(): Repository<'currency'> {
            return this.repositoryFactory.create('currency');
        },

        isCartTokenAvailable(): boolean {
            return Store.get('swOrder').isCartTokenAvailable;
        },

        hasLineItem(): boolean {
            return this.cart?.lineItems.filter((item: LineItem) => item.hasOwnProperty('id')).length > 0;
        },

        promotionCodeLineItems(): LineItem[] {
            return this.cart?.lineItems.filter((item: LineItem) => {
                return item.type === LineItemType.PROMOTION && item?.payload?.code;
            });
        },

        disabledAutoPromotion(): boolean {
            return Store.get('swOrder').disabledAutoPromotion;
        },

        promotionCodeTags: {
            get(): PromotionCodeTag[] {
                return Store.get('swOrder').promotionCodes;
            },

            set(promotionCodeTags: PromotionCodeTag[]) {
                Store.get('swOrder').setPromotionCodes(promotionCodeTags);
            },
        },
    },

    watch: {
        context: {
            deep: true,
            handler(): void {
                if (!this.user || !this.isCartTokenAvailable) {
                    return;
                }

                this.isLoading = true;
                void this.updateContext().finally(() => {
                    this.isLoading = false;
                });
            },
        },

        cart: {
            deep: true,
            immediate: true,
            handler: 'updatePromotionList',
        },

        promotionCodeTags: {
            handler: 'handlePromotionCodeTags',
        },

        'context.languageId'(languageId: EntityKey<'language'>) {
            if (!languageId) {
                return;
            }

            Contena.Store.get('context').api.languageId = languageId;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            if (!this.user) {
                void this.$nextTick(() => {
                    void this.$router.push({ name: 'sw.order.create.initial' });
                });
            }

            this.context = {
                ...this.context,
                currencyId: this.channelContext.context.currencyId,
                languageId: this.channelContext.context.languageIdChain[0],
                shippingMethodId: this.channelContext.shippingMethod.id,
                paymentMethodId: this.channelContext.paymentMethod.id,
                billingAddressId:
                    this.channelContext.user?.activeBillingAddress?.id ?? ('' as EntityKey<'user_address'>),
                shippingAddressId:
                    this.channelContext.user?.activeShippingAddress?.id ?? ('' as EntityKey<'user_address'>),
            };
        },

        async updateContext(): Promise<void> {
            if (!this.user) return;
            await Store.get('swOrder')
                .updateOrderContext({
                    context: this.context,
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                })
                .then(() => {
                    return this.loadCart();
                });
        },

        async loadCart() {
            if (!this.user) return;

            await Store.get('swOrder').getCart({
                channelId: this.user.channelId,
                contextToken: this.cart.token,
            });
        },

        onRemoveExistingCode(item: PromotionCodeTag) {
            if (item.isInvalid) {
                this.promotionCodeTags = this.promotionCodeTags.filter((tag: PromotionCodeTag) => tag.code !== item.code);

                return Promise.resolve();
            }

            return this.onRemoveItems([item.discountId]);
        },

        async onRemoveItems(lineItemKeys: string[]): Promise<void> {
            this.isLoading = true;
            if (!this.user) return;

            await Store.get('swOrder')
                .removeLineItems({
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                    lineItemKeys: lineItemKeys,
                })
                .then(() => {
                    // Remove promotion code tag if corresponding line item removed
                    lineItemKeys.forEach((key) => {
                        const removedTag = this.promotionCodeTags.find((tag: PromotionCodeTag) => tag.discountId === key);

                        if (removedTag) {
                            this.promotionCodeTags = this.promotionCodeTags.filter((item: PromotionCodeTag) => {
                                return item.discountId !== removedTag.discountId;
                            });
                        }
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        updatePromotionList() {
            // Synchronize the tags with the applied promotion line items and discard rejected codes
            this.promotionCodeTags = this.promotionCodeTags.flatMap((tag: PromotionCodeTag): PromotionCodeTag[] => {
                const matchedItem = this.promotionCodeLineItems.find(
                    (lineItem: LineItem): boolean => lineItem.payload?.code === tag.code,
                );

                if (matchedItem) {
                    return [
                        {
                            ...matchedItem.payload,
                            isInvalid: false,
                        } as PromotionCodeTag,
                    ];
                }

                return [];
            });

            // Add new items from promotionCodeLineItems which promotionCodeTags doesn't contain
            this.promotionCodeLineItems.forEach((lineItem: LineItem): void => {
                const matchedItem = this.promotionCodeTags.find(
                    (tag: PromotionCodeTag): boolean => tag.code === lineItem.payload?.code,
                );

                if (!matchedItem) {
                    this.promotionCodeTags = [
                        ...this.promotionCodeTags,
                        {
                            ...lineItem.payload,
                            isInvalid: false,
                        } as PromotionCodeTag,
                    ];
                }
            });
        },

        toggleAutomaticPromotions(visibility: boolean): void {
            this.showPromotionModal = visibility;
            if (visibility) {
                Store.get('swOrder').setDisabledAutoPromotion(true);
                return;
            }

            this.isLoading = true;
            void this.cartStoreService
                .enableAutomaticPromotions(this.cart.token, {
                    channelId: this.channelId,
                })
                .then(() => {
                    Store.get('swOrder').setDisabledAutoPromotion(false);

                    return this.loadCart();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onClosePromotionModal() {
            this.showPromotionModal = false;
            Store.get('swOrder').setDisabledAutoPromotion(false);
        },

        onSavePromotionModal() {
            this.showPromotionModal = false;
            Store.get('swOrder').setDisabledAutoPromotion(true);

            return this.loadCart().finally(() => {
                this.isLoading = false;
            });
        },

        modifyShippingCosts(amount: number) {
            const positiveAmount = Math.abs(amount);
            if (!this.cartDelivery) {
                return;
            }
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;
            this.isLoading = true;

            Store.get('swOrder')
                .modifyShippingCosts({
                    channelId: this.channelId,
                    contextToken: this.cart.token,
                    shippingCosts: this.cartDelivery.shippingCosts,
                })
                .catch((error) => {
                    this.$emit('error', error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        handlePromotionCodeTags(newValue: PromotionCodeTag[], oldValue: PromotionCodeTag[]) {
            this.promotionError = null;

            if (newValue.length < oldValue.length) {
                return;
            }

            const promotionCodeLength = this.promotionCodeTags.length;
            const latestTag = this.promotionCodeTags[promotionCodeLength - 1];

            if (newValue.length > oldValue.length) {
                void this.onSubmitCode(latestTag.code);
            }

            if (promotionCodeLength > 0 && latestTag.isInvalid) {
                this.promotionError = {
                    detail: this.$t('sw-order.createBase.textInvalidPromotionCode'),
                } as ContenaHttpError;
            }
        },

        async onSubmitCode(code: string): Promise<void> {
            this.isLoading = true;
            if (!this.user) return;

            await Store.get('swOrder')
                .addPromotionCode({
                    channelId: this.user?.channelId,
                    contextToken: this.cart.token,
                    code,
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
