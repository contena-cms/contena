/**
 * @sw-package after-sales
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'marketing',
    key: 'newsletter_recipient',
    roles: {
        viewer: {
            privileges: [
                'newsletter_recipient:read',
                'user_group:read',
                'channel:read',
                'salutation:read',
                'user:read',
                'tag:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['newsletter_recipient:update', Contena.Service('privileges').getPrivileges('media.creator')],
            dependencies: ['newsletter_recipient.viewer'],
        },
        creator: {
            privileges: ['newsletter_recipient:create'],
            dependencies: ['newsletter_recipient.viewer', 'newsletter_recipient.editor'],
        },
        deleter: {
            privileges: ['newsletter_recipient:delete'],
            dependencies: ['newsletter_recipient.viewer'],
        },
    },
});
