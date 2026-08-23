Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'tag',
    roles: {
        viewer: {
            privileges: [
                'tag:read',
                'media:read',
                'media_tag:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'tag:update',
                'media_tag:create',
                'media_tag:update',
                'media_tag:delete',
            ],
            dependencies: [
                'tag.viewer',
            ],
        },
        creator: {
            privileges: [
                'tag:create',
            ],
            dependencies: [
                'tag.viewer',
                'tag.editor',
            ],
        },
        deleter: {
            privileges: [
                'tag:delete',
                'media_tag:delete',
            ],
            dependencies: [
                'tag.viewer',
            ],
        },
    },
});
