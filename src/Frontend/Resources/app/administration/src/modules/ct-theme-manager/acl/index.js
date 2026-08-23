Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'theme',
    roles: {
        viewer: {
            privileges: [
                'theme:read',
                'theme_child:read',
                Contena.Service('privileges').getPrivileges('media.viewer'),
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'theme:update',
                'theme_child:update',
                Contena.Service('privileges').getPrivileges('media.creator'),
            ],
            dependencies: [
                'theme.viewer',
            ],
        },
        creator: {
            privileges: [
                'theme:create',
                'theme_child:create',
            ],
            dependencies: [
                'theme.viewer',
                'theme.editor',
            ],
        },
        deleter: {
            privileges: [
                'theme:delete',
                'theme_child:delete',
            ],
            dependencies: [
                'theme.viewer',
            ],
        },
    },
});
