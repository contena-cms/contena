<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Framework\Log\Package;

class UserEvents
{
    final public const CUSTOMER_WRITTEN_EVENT = 'user.written';

    final public const CUSTOMER_DELETED_EVENT = 'user.deleted';

    final public const CUSTOMER_LOADED_EVENT = 'user.loaded';

    final public const CUSTOMER_SEARCH_RESULT_LOADED_EVENT = 'user.search.result.loaded';

    final public const CUSTOMER_AGGREGATION_LOADED_EVENT = 'user.aggregation.result.loaded';

    final public const CUSTOMER_ID_SEARCH_RESULT_LOADED_EVENT = 'user.id.search.result.loaded';

    final public const CUSTOMER_ADDRESS_WRITTEN_EVENT = 'user_address.written';

    final public const CUSTOMER_ADDRESS_DELETED_EVENT = 'user_address.deleted';

    final public const CUSTOMER_ADDRESS_LOADED_EVENT = 'user_address.loaded';

    final public const CUSTOMER_ADDRESS_SEARCH_RESULT_LOADED_EVENT = 'user_address.search.result.loaded';

    final public const CUSTOMER_ADDRESS_AGGREGATION_LOADED_EVENT = 'user_address.aggregation.result.loaded';

    final public const CUSTOMER_ADDRESS_ID_SEARCH_RESULT_LOADED_EVENT = 'user_address.id.search.result.loaded';

    final public const CUSTOMER_CHANGED_PAYMENT_METHOD_EVENT = 'checkout.user.changed-payment-method';

    final public const CUSTOMER_BEFORE_LOGIN_EVENT = 'checkout.user.before.login';

    final public const CUSTOMER_LOGIN_EVENT = 'checkout.user.login';

    final public const CUSTOMER_LOGOUT_EVENT = 'checkout.user.logout';

    final public const CUSTOMER_REGISTER_EVENT = 'checkout.user.register';

    final public const CUSTOMER_GROUP_WRITTEN_EVENT = 'user_group.written';

    final public const CUSTOMER_GROUP_DELETED_EVENT = 'user_group.deleted';

    final public const CUSTOMER_GROUP_LOADED_EVENT = 'user_group.loaded';

    final public const CUSTOMER_GROUP_SEARCH_RESULT_LOADED_EVENT = 'user_group.search.result.loaded';

    final public const CUSTOMER_GROUP_AGGREGATION_LOADED_EVENT = 'user_group.aggregation.result.loaded';

    final public const CUSTOMER_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'user_group.id.search.result.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_WRITTEN_EVENT = 'user_group_discount.written';

    final public const CUSTOMER_GROUP_DISCOUNT_DELETED_EVENT = 'user_group_discount.deleted';

    final public const CUSTOMER_GROUP_DISCOUNT_LOADED_EVENT = 'user_group_discount.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_SEARCH_RESULT_LOADED_EVENT = 'user_group_discount.search.result.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_AGGREGATION_LOADED_EVENT = 'user_group_discount.aggregation.result.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_ID_SEARCH_RESULT_LOADED_EVENT = 'user_group_discount.id.search.result.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_WRITTEN_EVENT = 'user_group_translation.written';

    final public const CUSTOMER_GROUP_TRANSLATION_DELETED_EVENT = 'user_group_translation.deleted';

    final public const CUSTOMER_GROUP_TRANSLATION_LOADED_EVENT = 'user_group_translation.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'user_group_translation.search.result.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'user_group_translation.aggregation.result.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'user_group_translation.id.search.result.loaded';

    final public const MAPPING_REGISTER_ADDRESS_BILLING = 'checkout.user.channel.register.billing_address';

    final public const MAPPING_REGISTER_ADDRESS_SHIPPING = 'checkout.user.channel.register.shipping_address';

    final public const MAPPING_REGISTER_CUSTOMER = 'checkout.user.channel.register.user';

    final public const MAPPING_CUSTOMER_PROFILE_SAVE = 'checkout.user.channel.profile.update';

    final public const MAPPING_ADDRESS_CREATE = 'checkout.user.channel.address.create';
}
