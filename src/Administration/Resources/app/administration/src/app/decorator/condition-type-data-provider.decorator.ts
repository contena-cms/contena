/**
 * @sw-package fundamentals@after-sales
 */
import type RuleConditionService from '../service/rule-condition.service';

const { Application } = Contena;

/**
 * Execution contexts in which a condition can be evaluated. The rule builder filters the
 * "Add condition" dropdown by scope so e.g. a line-item rule only sees line-item-aware
 * conditions.
 *
 * @private
 */
export const SCOPES = {
    GLOBAL: 'global',
    CART: 'cart',
    CHECKOUT: 'checkout',
    LINE_ITEM: 'lineItem',
    FLOW: 'flow',
} as const;

/**
 * @private
 */
export type RuleScope = (typeof SCOPES)[keyof typeof SCOPES];

/**
 * Bucket a condition belongs to. The rule builder lists conditions grouped by this
 * value (User, Cart, Order, …).
 *
 * @private
 */
export const GROUPS = {
    GENERAL: 'general',
    CART: 'cart',
    ITEM: 'item',
    CUSTOMER: 'user',
    PROMOTION: 'promotion',
    ORDER: 'order',
    MISC: 'misc',
} as const;

/**
 * @private
 */
export type RuleGroup = (typeof GROUPS)[keyof typeof GROUPS];

/**
 * Components used to render a condition's editor inside the rule builder. Most
 * conditions reuse `sw-condition-generic`, which builds its form from the backend rule
 * definition.
 *
 * @private
 */
export const COMPONENTS = {
    GENERIC: 'sw-condition-generic',
    GENERIC_LINE_ITEM: 'sw-condition-generic-line-item',
    LINE_ITEM: 'sw-condition-line-item',
    LINE_ITEM_WITH_QUANTITY: 'sw-condition-line-item-with-quantity',
    LINE_ITEM_PROPERTY: 'sw-condition-line-item-property',
    LINE_ITEM_CUSTOM_FIELD: 'sw-condition-line-item-custom-field',
    LINE_ITEM_GOODS_TOTAL: 'sw-condition-line-item-goods-total',
    CUSTOMER_CUSTOM_FIELD: 'sw-condition-user-custom-field',
    ORDER_CUSTOM_FIELD: 'sw-condition-order-custom-field',
    BILLING_ZIP_CODE: 'sw-condition-billing-zip-code',
    SHIPPING_ZIP_CODE: 'sw-condition-shipping-zip-code',
    GOODS_COUNT: 'sw-condition-goods-count',
    GOODS_PRICE: 'sw-condition-goods-price',
    DATE_RANGE: 'sw-condition-date-range',
    TIME_RANGE: 'sw-condition-time-range',
    IS_ALWAYS_VALID: 'sw-condition-is-always-valid',
} as const;

/**
 * @private
 */
export type ConditionComponent = (typeof COMPONENTS)[keyof typeof COMPONENTS];

/**
 * @private
 */
export type ConditionDefinition = {
    type: string;
    component: ConditionComponent;
    label: string;
    scopes: RuleScope[];
    group: RuleGroup;
    removedInFeature?: string; // e.g. 'v6.8.0.0'
    replacement?: string; // condition type that supersedes this one
};

/**
 * @private
 */
export type AwarenessConfigurationDefinition = {
    name: string;
    config: {
        notEquals?: string[];
        equalsAny?: string[];
        snippet?: string;
    };
};

/**
 * @private
 */
export const CONDITIONS: ConditionDefinition[] = [
    {
        type: 'dateRange',
        component: COMPONENTS.DATE_RANGE,
        label: 'global.sw-condition.condition.dateRangeRule.label',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'timeRange',
        component: COMPONENTS.TIME_RANGE,
        label: 'global.sw-condition.condition.timeRangeRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'numberOfReviews',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.numberOfReviews',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userOrderCount',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderCountRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userDaysSinceLastOrder',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.daysSinceLastOrderRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'channel',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.channelRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'currency',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.currencyRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'language',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.languageRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'cartTaxDisplay',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.cartTaxDisplay.label',
        scopes: [SCOPES.CART],
        group: GROUPS.GENERAL,
    },
    {
        type: 'userBillingCountry',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.billingCountryRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userBillingStreet',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.billingStreetRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userBillingZipCode',
        component: COMPONENTS.BILLING_ZIP_CODE,
        label: 'global.sw-condition.condition.billingZipCodeRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userCustomerGroup',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userGroupRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userRequestedGroup',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userRequestedGroupRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userTag',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userTagRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userCustomerNumber',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userNumberRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userDifferentAddresses',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.differentAddressesRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userEmail',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.emailRule.label',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userLastName',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.lastNameRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userIsCompany',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.isCompanyRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userIsGuest',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.isGuestRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userIsNewsletterRecipient',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.isNewsletterRecipient',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userShippingCountry',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.shippingCountryRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userShippingStreet',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.shippingStreetRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userShippingZipCode',
        component: COMPONENTS.SHIPPING_ZIP_CODE,
        label: 'global.sw-condition.condition.shippingZipCodeRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userLoggedIn',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userLoggedInRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userBillingCity',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.billingCityRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userBillingState',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.billingStateRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userIsActive',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userIsActiveRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userShippingCity',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.shippingCityRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userShippingState',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.shippingStateRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userAge',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userAgeRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userDaysSinceLastLogin',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userDaysSinceLastLogin',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userDaysSinceFirstLogin',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userDaysSinceFirstLogin',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userAffiliateCode',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userAffiliateCodeRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userCampaignCode',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userCampaignCodeRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'orderAffiliateCode',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderAffiliateCodeRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderCampaignCode',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderCampaignCodeRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'cartCartAmount',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.cartAmountRule',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartPositionPrice',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.cartPositionPrice',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartGoodsCount',
        component: COMPONENTS.GOODS_COUNT,
        label: 'global.sw-condition.condition.goodsCountRule',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartTotalPurchasePrice',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.cartTotalPurchasePrice',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartGoodsPrice',
        component: COMPONENTS.GOODS_PRICE,
        label: 'global.sw-condition.condition.goodsPriceRule',
        scopes: [SCOPES.CART],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemOfType',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItem',
        component: COMPONENTS.LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemsInCartCount',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.lineItemsInCartCountRule',
        scopes: [SCOPES.CART],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemTotalPrice',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemTotalPriceRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemUnitPrice',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemUnitPriceRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemWithQuantity',
        component: COMPONENTS.LINE_ITEM_WITH_QUANTITY,
        label: 'global.sw-condition.condition.lineItemWithQuantityRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemPerItemQuantity',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemPerItemQuantityRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartHasDeliveryFreeItem',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.hasDeliveryFreeItemRule',
        scopes: [SCOPES.CART],
        group: GROUPS.ITEM,
    },
    {
        type: 'dayOfWeek',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.dayOfWeekRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'cartWeight',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.weightOfCartRule',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartVolume',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.volumeOfCartRule',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartShippingCost',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.cartShippingCost',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartLineItemTag',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemTagRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'alwaysValid',
        component: COMPONENTS.IS_ALWAYS_VALID,
        label: 'global.sw-condition.condition.alwaysValidRule',
        scopes: [SCOPES.GLOBAL],
        group: GROUPS.GENERAL,
    },
    {
        type: 'cartLineItemProperty',
        component: COMPONENTS.LINE_ITEM_PROPERTY,
        label: 'global.sw-condition.condition.lineItemPropertyRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemIsNew',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemIsNewRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemOfManufacturer',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemOfManufacturerRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemPurchasePrice',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemPurchasePriceRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemCreationDate',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemCreationDateRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemReleaseDate',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemReleaseDateRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemClearanceSale',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemClearanceSale',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemPromoted',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemPromotedRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemInCategory',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemInCategoryRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemInProductStream',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemInProductStreamRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemTaxation',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemTaxationRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemDimensionWidth',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemDimensionWidthRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemDimensionHeight',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemDimensionHeightRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemDimensionLength',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemDimensionLengthRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemDimensionWeight',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemDimensionWeightRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemDimensionVolume',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemDimensionVolumeRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemListPrice',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemListPriceRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemListPriceRatio',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemListPriceRatioRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemCustomField',
        component: COMPONENTS.LINE_ITEM_CUSTOM_FIELD,
        label: 'global.sw-condition.condition.lineItemCustomFieldRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemActualStock',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemActualStockRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'userCustomField',
        component: COMPONENTS.CUSTOMER_CUSTOM_FIELD,
        label: 'global.sw-condition.condition.userCustomFieldRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'paymentMethod',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.paymentMethodRule',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'shippingMethod',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.shippingMethodRule',
        scopes: [SCOPES.CART],
        group: GROUPS.CART,
    },
    {
        type: 'cartLineItemGoodsTotal',
        component: COMPONENTS.LINE_ITEM_GOODS_TOTAL,
        label: 'global.sw-condition.condition.lineItemGoodsTotalRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.CART,
    },
    {
        type: 'userOrderTotalAmount',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderTotalAmountRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'promotionLineItem',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.promotionLineItemRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.PROMOTION,
    },
    {
        type: 'promotionCodeOfType',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.promotionCodeOfType',
        scopes: [SCOPES.CART],
        group: GROUPS.PROMOTION,
    },
    {
        type: 'promotionsInCartCount',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.promotionsInCartCountRule',
        scopes: [SCOPES.CART],
        group: GROUPS.PROMOTION,
    },
    {
        type: 'promotionValue',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.promotionValueRule',
        scopes: [SCOPES.CART],
        group: GROUPS.PROMOTION,
    },
    {
        type: 'userBirthday',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userBirthdayRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userCreatedByAdmin',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userCreatedByAdminRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'userSalutation',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.userSalutationRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.CUSTOMER,
    },
    {
        type: 'cartLineItemProductStates',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemProductStates',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
        removedInFeature: 'v6.8.0.0',
        replacement: 'cartLineItemProductType',
    },
    {
        type: 'cartLineItemProductType',
        component: COMPONENTS.GENERIC_LINE_ITEM,
        label: 'global.sw-condition.condition.lineItemProductType',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'orderDaysSincePlaced',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderDaysSincePlacedRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderTag',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderTagRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderTrackingCode',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderTrackingCodeRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderDeliveryStatus',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderDeliveryStatusRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'adminSalesChannelSource',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.adminSalesChannelSourceRule',
        scopes: [SCOPES.CHECKOUT],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderTransactionStatus',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderTransactionStatusRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderStatus',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderStatusRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderCreatedByAdmin',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderCreatedByAdminRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderCustomField',
        component: COMPONENTS.ORDER_CUSTOM_FIELD,
        label: 'global.sw-condition.condition.orderCustomFieldRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderDocumentType',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderDocumentTypeRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'orderDocumentTypeSent',
        component: COMPONENTS.GENERIC,
        label: 'global.sw-condition.condition.orderDocumentTypeSentRule',
        scopes: [SCOPES.FLOW],
        group: GROUPS.ORDER,
    },
    {
        type: 'cartLineItemPropertyValue',
        component: COMPONENTS.LINE_ITEM_PROPERTY,
        label: 'global.sw-condition.condition.lineItemPropertyValueRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
    {
        type: 'cartLineItemVariantValue',
        component: COMPONENTS.LINE_ITEM_PROPERTY,
        label: 'global.sw-condition.condition.lineItemVariantValueRule',
        scopes: [SCOPES.LINE_ITEM],
        group: GROUPS.ITEM,
    },
];

/**
 * Per-assignment restrictions on which conditions a rule may contain.
 *
 * Each entry binds a rule-aware entity (e.g. `cartPromotions`, `shippingMethodPrices`)
 * to a restriction config:
 *
 * - `notEquals` — condition types that are forbidden in this assignment.
 * - `equalsAny` — condition types of which at least one must be present.
 *
 * The rule builder consumes this to disable invalid conditions and render tooltips.
 *
 * @private
 */
export const AWARENESS_CONFIGURATIONS = (service: RuleConditionService): AwarenessConfigurationDefinition[] => {
    const orderRestrictions = service
        .getRestrictionsByGroup(GROUPS.ORDER)
        .filter((type) => service.getByType(type).scopes.indexOf(SCOPES.CHECKOUT) === -1);

    return [
        {
            name: 'personaPromotions',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                equalsAny: ['alwaysValid', ...service.getRestrictionsByGroup(GROUPS.CUSTOMER)],
                snippet: 'sw-restricted-rules.restrictedAssignment.personaPromotions',
            },
        },
        {
            name: 'orderPromotions',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.orderPromotions',
            },
        },
        {
            name: 'cartPromotions',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.cartPromotions',
            },
        },
        {
            name: 'promotionSetGroups',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.promotionSetGroups',
            },
        },
        {
            name: 'promotionDiscounts',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.promotionDiscounts',
            },
        },
        {
            name: 'shippingMethodPriceCalculations',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethodPriceCalculations',
            },
        },
        {
            name: 'shippingMethodPrices',
            config: {
                notEquals: ['cartCartAmount', 'cartShippingCost', ...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethodPrices',
            },
        },
        {
            name: 'paymentMethods',
            config: {
                notEquals: [...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.paymentMethods',
            },
        },
        {
            name: 'shippingMethods',
            config: {
                notEquals: [...orderRestrictions],
                snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethods',
            },
        },
    ];
};

/**
 * @private
 */
export const ruleConditionTypeDataProvider = (ruleConditionService: RuleConditionService): RuleConditionService => {
    CONDITIONS.forEach(({ type, removedInFeature, replacement, ...condition }) => {
        if (removedInFeature) {
            ruleConditionService.registerDeprecation(type, {
                version: removedInFeature,
                label: condition.label,
                replacement,
            });
        }

        if (removedInFeature && Contena.Feature.isActive(removedInFeature)) {
            return;
        }

        ruleConditionService.addCondition(type, condition);
    });

    AWARENESS_CONFIGURATIONS(ruleConditionService).forEach(({ name, config }) => {
        ruleConditionService.addAwarenessConfiguration(name, config);
    });

    return ruleConditionService;
};

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService: RuleConditionService) => {
    return ruleConditionTypeDataProvider(ruleConditionService);
});
