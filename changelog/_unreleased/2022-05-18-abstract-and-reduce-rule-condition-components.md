---
title: Abstract and reduce rule condition components
issue: NEXT-20345
flag: V6_5_0_0
author: d.neustadt
author_email: d.neustadt@contena.cn
author_github: dneustadt
---
# Core
* Added `Contena\Core\Framework\Rule\RuleConfig`
* Added method `Contena\Core\Framework\Rule\Rule::getConfig()`
___
# Administration
* Added components `ct-condition-generic` and `ct-condition-generic-line-item`
* Added mixin `generic-condition`
* Deprecated the following components:
    * `ct-condition-billing-country`
    * `ct-condition-billing-street`
    * `ct-condition-cart-amount`
    * `ct-condition-cart-has-delivery-free-item`
    * `ct-condition-cart-position-price`
    * `ct-condition-cart-tax-display`
    * `ct-condition-currency`
    * `ct-condition-customer-group`
    * `ct-condition-customer-logged-in`
    * `ct-condition-customer-number`
    * `ct-condition-customer-tag`
    * `ct-condition-day-of-week`
    * `ct-condition-days-since-last-order`
    * `ct-condition-different-addresses`
    * `ct-condition-email`
    * `ct-condition-is-company`
    * `ct-condition-is-guest`
    * `ct-condition-is-new-customer`
    * `ct-condition-is-newsletter-recipient`
    * `ct-condition-language`
    * `ct-condition-last-name`
    * `ct-condition-line-item-actual-stock`
    * `ct-condition-line-item-clearance-sale`
    * `ct-condition-line-item-creation-date`
    * `ct-condition-line-item-dimension-height`
    * `ct-condition-line-item-dimension-length`
    * `ct-condition-line-item-dimension-volume`
    * `ct-condition-line-item-dimension-weight`
    * `ct-condition-line-item-dimension-width`
    * `ct-condition-line-item-in-product-stream`
    * `ct-condition-line-item-is-new`
    * `ct-condition-line-item-list-price`
    * `ct-condition-line-item-list-price-ratio`
    * `ct-condition-line-item-of-manufacturer`
    * `ct-condition-line-item-of-type`
    * `ct-condition-line-item-promoted`
    * `ct-condition-line-item-release-date`
    * `ct-condition-line-item-stock`
    * `ct-condition-line-item-tag`
    * `ct-condition-line-item-taxation`
    * `ct-condition-line-item-total-price`
    * `ct-condition-line-item-unit-price`
    * `ct-condition-line-items-in-cart-count`
    * `ct-condition-order-count`
    * `ct-condition-order-total-amount`
    * `ct-condition-payment-method`
    * `ct-condition-promotion-code-of-type`
    * `ct-condition-promotion-line-item`
    * `ct-condition-promotion-value`
    * `ct-condition-promotions-in-cart-count`
    * `ct-condition-sales-channel`
    * `ct-condition-shipping-country`
    * `ct-condition-shipping-method`
    * `ct-condition-shipping-street`
    * `ct-condition-volume-of-cart`
    * `ct-condition-weight-of-cart`

___
# Next Major Version Changes
## Deprecated rule condition components will be removed:
* If you used or extended any of these components, use/extend `ct-condition-generic` or `ct-condition-generic-line-item` instead and refer to `this.condition.type` to introduce changes for a specific type of condition.
