/**
 * @sw-package checkout
 */

Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'users',
    key: 'user',
    roles: {
        viewer: {
            privileges: [
                'user:read',
                'user_address:read',
                'user_group:read',
                'salutation:read',
                'channel:read',
                'channel_domain:read',
                'channel_tracking_customer:read',
                'payment_method:read',
                'country:read',
                'country_state:read',
                'tag:read',
                'order:read',
                'order_customer:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'state_machine_state:read',
                'currency:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'user:update',
                'user_address:update',
                'user_address:create',
                'user_address:delete',
                'tag:create',
                'user_tag:create',
                'user_group:update',
                'custom_field:update',
                'system_config:read',
                'api_proxy_imitate-user',
            ],
            dependencies: ['user.viewer'],
        },
        creator: {
            privileges: ['user:create', 'number_range:read'],
            dependencies: ['user.viewer', 'user.editor'],
        },
        deleter: {
            privileges: ['user:delete'],
            dependencies: ['user.viewer'],
        },
    },
});
