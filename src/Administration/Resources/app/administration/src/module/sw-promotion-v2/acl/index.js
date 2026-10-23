/**
 * @sw-package checkout
 */
const { Service } = Contena;

Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'marketing',
    key: 'promotion',
    roles: {
        viewer: {
            privileges: [
                'promotion:read',
                'promotion_channel:read',
                'user:read',
                'rule:read',
                'channel:read',
                'promotion_discount:read',
                'promotion_discount_prices:read',
                'promotion_individual_code:read',
                'rule_condition:read',
                'currency:read',
                'promotion_discount_rule:read',
                'promotion_setgroup:read',
                'promotion_setgroup_rule:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'promotion:update',
                'promotion_channel:create',
                'promotion_channel:delete',
                'promotion_persona_customer:create',
                'promotion_persona_customer:delete',
                'promotion_order_rule:create',
                'promotion_order_rule:delete',
                'promotion_persona_rule:create',
                'promotion_persona_rule:delete',
                'promotion_cart_rule:create',
                'promotion_cart_rule:delete',
                'promotion_discount:create',
                'promotion_discount:update',
                'promotion_discount:delete',
                'promotion_discount_rule:create',
                'promotion_discount_prices:create',
                'promotion_individual_code:create',
                'promotion_individual_code:delete',
                'promotion_setgroup:create',
                'promotion_setgroup_rule:create',
                Contena.Service('privileges').getPrivileges('rule.creator'),
            ],
            dependencies: ['promotion.viewer'],
        },
        creator: {
            privileges: ['promotion:create'],
            dependencies: ['promotion.viewer', 'promotion.editor'],
        },
        deleter: {
            privileges: ['promotion:delete'],
            dependencies: ['promotion.viewer'],
        },
    },
});
