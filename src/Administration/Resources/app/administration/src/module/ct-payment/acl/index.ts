Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'payment',
    roles: {
        viewer: {
            privileges: [
                'payment_order:read',
                'payment_order_transaction:read',
                'payment_refund:read',
                'payment_app:read',
                'payment_channel:read',
                'payment_channel_method:read',
                'payment_channel_config:read',
                'payment_app_channel_method:read',
                'state_machine_state:read',
                'rule:read',
            ],
            dependencies: [],
        },
        settings: {
            privileges: [
                'payment_app:create',
                'payment_app:update',
                'payment_app:delete',
                'payment_channel_config:create',
                'payment_channel_config:update',
                'payment_channel_config:delete',
                'payment_app_channel_method:create',
                'payment_app_channel_method:update',
                'payment_app_channel_method:delete',
            ],
            dependencies: ['payment.viewer'],
        },
    },
});
