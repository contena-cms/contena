/**
 * @sw-package checkout
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    key: 'sw-payments',
    parent: 'settings',
    roles: {
        viewer: {
            privileges: [Contena.Service('privileges').getPrivileges('payment.viewer'), 'app.ContenaPayments'],
            dependencies: ['app.ContenaPayments'],
        },
        editor: {
            privileges: [],
            dependencies: ['sw-payments.viewer'],
        },
        creator: {
            privileges: [],
            dependencies: ['sw-payments.viewer', 'sw-payments.editor'],
        },
    },
});
