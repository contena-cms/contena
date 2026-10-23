import template from './sw-order-create-base.html.twig';

/**
 * @sw-package checkout
 */

const { Store, Utils, Data, Service, Mixin } = Contena;
const { Criteria } = Data;
const { get, format, array } = Utils;

/**
 * @deprecated tag:v6.8.0 - will be removed, is not used anymore
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['error'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isLoadingDetail: false,
            address: {
                data: null,
            },
            showAddressModal: false,
            addAddressModalTitle: null,
            editAddressModalTitle: null,
            promotionError: null,
            showPromotionModal: false,
            disabledAutoPromotionChecked: false,
        };
    },

    computed: {
        cartErrors() {
            return Store.get('swOrder').cartErrors;
        },

        userRepository() {
            return Service('repositoryFactory').create('user');
        },

        userAddressRepository() {
            return Service('repositoryFactory').create('user_address');
        },

        currencyRepository() {
            return Service('repositoryFactory').create('currency');
        },

        userAddressCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            return criteria;
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('channel')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },

        orderDate() {
            const today = new Date();
            return format.date(today);
        },

        user() {
            return Store.get('swOrder').user;
        },

        channelId() {
            return this.user?.channelId ?? '';
        },

        isCustomerActive() {
            return Store.get('swOrder').isCustomerActive;
        },

        cart() {
            return Store.get('swOrder').cart;
        },

        cartLineItems() {
            return this.cart.lineItems;
        },

        cartAutomaticPromotionItems() {
            return this.cartLineItems.filter((item) => item.type === 'promotion' && item.payload.code === '');
        },

        cartPrice() {
            return this.cart.price;
        },

        currency() {
            return Store.get('swOrder').context.currency;
        },

        cartDelivery() {
            return get(this.cart, 'deliveries[0]', null);
        },

        promotionCodeTags: {
            get() {
                return Store.get('swOrder').promotionCodes;
            },

            set(promotionCodeTags) {
                Store.get('swOrder').setPromotionCodes(promotionCodeTags);
            },
        },

        cartDeliveryDiscounts() {
            return array.slice(this.cart.deliveries, 1) || [];
        },

        filteredCalculatedTaxes() {
            if (!this.cartPrice || !this.cartPrice.calculatedTaxes) {
                return [];
            }

            return this.sortByTaxRate(this.cartPrice.calculatedTaxes).filter((price) => price.tax !== 0);
        },

        promotionCodeLineItems() {
            return this.cartLineItems.filter((item) => item.type === 'promotion' && get(item, 'payload.code'));
        },

        hasLineItem() {
            return this.cartLineItems.filter((item) => item.hasOwnProperty('id')).length > 0;
        },

        shippingCostsDetail() {
            if (!this.cartDelivery) {
                return null;
            }

            const calcTaxes = this.sortByTaxRate(this.cartDelivery.shippingCosts.calculatedTaxes);
            const decorateCalcTaxes = calcTaxes.map((item) => {
                return this.$t(
                    'sw-order.createBase.shippingCostsTax',
                    {
                        taxRate: item.taxRate,
                        tax: format.currency(item.tax, this.currency.isoCode),
                    },
                    0,
                );
            });

            return `${this.$t('sw-order.createBase.tax')}<br>${decorateCalcTaxes.join('<br>')}`;
        },

        disabledAutoPromotionVisibility: {
            get() {
                return this.disabledAutoPromotionChecked;
            },
            set(visibility) {
                this.switchAutomaticPromotions(visibility);
            },
        },

        taxStatus() {
            return get(this.cart, 'price.taxStatus', '');
        },

        displayRounded() {
            if (!this.cartPrice) {
                return false;
            }
            return this.cartPrice.rawTotal !== this.cartPrice.totalPrice;
        },

        orderTotal() {
            if (!this.cartPrice) {
                return 0;
            }

            if (this.displayRounded) {
                return this.cartPrice.rawTotal;
            }

            return this.cartPrice.totalPrice;
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },
    },

    watch: {
        cart: {
            deep: true,
            handler: 'updatePromotionList',
        },

        promotionCodeTags: {
            handler: 'handlePromotionCodeTags',
        },

        cartErrors: {
            handler(newValue) {
                if (!newValue || newValue.length === 0) {
                    return;
                }

                Object.values(newValue).forEach((value) => {
                    switch (value.level) {
                        case 0: {
                            this.createNotificationSuccess({
                                message: value.message,
                            });
                            break;
                        }

                        case 10: {
                            this.createNotificationWarning({
                                message: value.message,
                            });
                            break;
                        }

                        default: {
                            this.createNotificationError({
                                message: value.message,
                            });
                            break;
                        }
                    }
                });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const { user } = this.$route.params;

            if (!user) {
                return;
            }

            Store.get('swOrder').setCustomer(user);
            this.onSelectExistingCustomer(user.id);
        },

        async createCart(channelId) {
            await Store.get('swOrder').createCart({ channelId });
        },

        async loadCart() {
            if (!this.cart.token || this.cart.lineItems.length === 0) return;
            this.updateLoading(true);

            Store.get('swOrder')
                .getCart({
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                })
                .finally(() => this.updateLoading(false));
        },

        async onSelectExistingCustomer(userId) {
            this.isLoadingDetail = true;

            try {
                const user = await this.userRepository.get(userId, Contena.Context.api, this.defaultCriteria);

                if (!this.cart.token) {
                    await this.createCart(user.channelId);
                }

                this.setCustomer(user);
                this.setCurrency(user);

                await this.updateCustomerContext();
            } catch (error) {
                let message = this.$t('sw-order.create.messageSwitchCustomerError');
                const errorCode = error.response?.data?.errors?.[0]?.code;

                if (errorCode) {
                    const messageKey = `global.error-codes.${errorCode}`;
                    const translatedMessage = this.$t(messageKey);

                    if (translatedMessage !== messageKey) {
                        message = `${message}: ${translatedMessage}`;
                    }
                }

                this.createNotificationError({
                    message,
                });
            } finally {
                this.isLoadingDetail = false;
            }
        },

        async updateCustomerContext() {
            await Store.get('swOrder').updateCustomerContext({
                userId: this.user.id,
                channelId: this.user.channelId,
                contextToken: this.cart.token,
            });
        },

        setCustomer(user) {
            Store.get('swOrder').selectExistingCustomer({ user });
        },

        setCurrency(user) {
            this.currencyRepository.get(user.channel.currencyId).then((currency) => {
                Store.get('swOrder').setCurrency(currency);
            });
        },

        onEditBillingAddress() {
            const contextId = 'billingAddressId';
            const contextDataKey = 'billingAddress';
            const contextDataDefaultId = 'defaultBillingAddressId';
            const data = this.user[contextDataKey] ? this.user[contextDataKey] : this.user.defaultBillingAddress;

            this.addAddressModalTitle = this.$t('sw-order.addressSelection.modalTitleAddBillingAddress');
            this.editAddressModalTitle = this.$t('sw-order.addressSelection.modalTitleEditBillingAddress');
            this.address = {
                contextId,
                contextDataKey,
                contextDataDefaultId,
                data,
            };
            this.showAddressModal = true;
        },

        onEditShippingAddress() {
            const contextId = 'shippingAddressId';
            const contextDataKey = 'shippingAddress';
            const contextDataDefaultId = 'defaultShippingAddressId';
            const data = this.user[contextDataKey]
                ? this.user[contextDataKey]
                : this.user.defaultShippingAddress;

            this.addAddressModalTitle = this.$t('sw-order.addressSelection.modalTitleAddShippingAddress');
            this.editAddressModalTitle = this.$t('sw-order.addressSelection.modalTitleEditShippingAddress');
            this.address = {
                contextId,
                contextDataKey,
                contextDataDefaultId,
                data,
            };
            this.showAddressModal = true;
        },

        setCustomerAddress({ contextId, data }) {
            this.user[contextId] = data.id;
            const availableCustomerAddresses = [
                {
                    id: this.user.billingAddressId,
                    dataKey: 'billingAddress',
                },
                {
                    id: this.user.shippingAddressId,
                    dataKey: 'shippingAddress',
                },
                {
                    id: this.user.defaultBillingAddressId,
                    dataKey: 'defaultBillingAddress',
                },
                {
                    id: this.user.defaultShippingAddressId,
                    dataKey: 'defaultShippingAddress',
                },
            ];

            this.userAddressRepository
                .get(data.id, Contena.Context.api, this.userAddressCriteria)
                .then((updatedAddress) => {
                    availableCustomerAddresses.forEach((userAddress) => {
                        if (userAddress.id === data.id) {
                            this.user[userAddress.dataKey] = updatedAddress;
                        }
                    });

                    this.setCustomer(this.user);
                });
        },

        closeModal() {
            this.showAddressModal = false;
            this.address.data = null;
        },

        save() {
            this.closeModal();
        },

        onSaveItem(item) {
            this.updateLoading(true);

            Store.get('swOrder')
                .saveLineItem({
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                    item,
                })
                .finally(() => this.updateLoading(false));
        },

        onRemoveItems(lineItemKeys) {
            this.updateLoading(true);

            Store.get('swOrder')
                .removeLineItems({
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                    lineItemKeys: lineItemKeys,
                })
                .then(() => {
                    // Remove promotion code tag if corresponding line item removed
                    lineItemKeys.forEach((key) => {
                        const removedTag = this.promotionCodeTags.find((tag) => tag.discountId === key);
                        if (removedTag) {
                            this.promotionCodeTags = this.promotionCodeTags.filter((item) => {
                                return item.discountId !== removedTag.discountId;
                            });
                        }
                    });
                })
                .finally(() => this.updateLoading(false));
        },

        updateLoading(loadingValue) {
            this.isLoading = loadingValue;
        },

        sortByTaxRate(price) {
            return price.sort((prev, current) => {
                return prev.taxRate - current.taxRate;
            });
        },

        onSubmitCode(code) {
            this.updateLoading(true);

            Store.get('swOrder')
                .addPromotionCode({
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                    code,
                })
                .finally(() => this.updateLoading(false));
        },

        onRemoveExistingCode(item) {
            if (item.isInvalid) {
                this.promotionCodeTags = this.promotionCodeTags.filter((tag) => tag.code !== item.code);
            } else {
                this.onRemoveItems([item.discountId]);
            }
        },

        updatePromotionList() {
            // Update data and isInvalid flag for each item in promotionCodeTags
            this.promotionCodeTags = this.promotionCodeTags.map((tag) => {
                const matchedItem = this.promotionCodeLineItems.find((lineItem) => lineItem.payload.code === tag.code);

                if (matchedItem) {
                    return { ...matchedItem.payload, isInvalid: false };
                }

                return { ...tag, isInvalid: true };
            });

            // Add new items from promotionCodeLineItems which promotionCodeTags doesn't contain
            this.promotionCodeLineItems.forEach((lineItem) => {
                const matchedItem = this.promotionCodeTags.find((tag) => tag.code === lineItem.payload.code);

                if (!matchedItem) {
                    this.promotionCodeTags = [...this.promotionCodeTags, { ...lineItem.payload, isInvalid: false }];
                }
            });
        },

        handlePromotionCodeTags(newValue, oldValue) {
            this.promotionError = null;

            if (newValue.length < oldValue.length) {
                return;
            }

            const promotionCodeLength = this.promotionCodeTags.length;
            const latestTag = this.promotionCodeTags[promotionCodeLength - 1];

            if (newValue.length > oldValue.length) {
                this.onSubmitCode(latestTag.code);
            }

            if (promotionCodeLength > 0 && latestTag.isInvalid) {
                this.promotionError = {
                    detail: this.$t('sw-order.createBase.textInvalidPromotionCode'),
                };
            }
        },

        onShippingChargeEdited() {
            this.updateLoading(true);

            Store.get('swOrder')
                .modifyShippingCosts({
                    channelId: this.user.channelId,
                    contextToken: this.cart.token,
                    shippingCosts: this.cartDelivery.shippingCosts,
                })
                .catch((error) => {
                    this.$emit('error', error);
                })
                .finally(() => {
                    this.updateLoading(false);
                });
        },

        switchAutomaticPromotions(visibility) {
            this.disabledAutoPromotionChecked = visibility;
            this.showPromotionModal = visibility;
            if (!this.showPromotionModal) {
                this.enableAutomaticPromotions();
            }
        },

        enableAutomaticPromotions() {
            this.updateLoading(true);
            const additionalParams = {
                channelId: this.user.channelId,
            };
            Service('cartStoreService')
                .enableAutomaticPromotions(this.cart.token, additionalParams)
                .then(() => {
                    this.loadCart();
                });
        },

        onClosePromotionModal() {
            this.showPromotionModal = false;
            this.disabledAutoPromotionChecked = false;
        },

        onSavePromotionModal() {
            this.showPromotionModal = false;
            this.disabledAutoPromotionChecked = true;

            this.loadCart();
        },

        onShippingChargeUpdated(amount) {
            const positiveAmount = Math.abs(amount);
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;
        },
    },
};
