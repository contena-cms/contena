Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'member',
    roles: {
        viewer: {
            privileges: [
                'member:read',
                'member_address:read',
                'member_group:read',
                'channel:read',
                'channel_domain:read',
                'language:read',
                'country:read',
                'region:read',
                'tag:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'member:update',
                'member_address:create',
                'member_address:update',
                'member_address:delete',
                'member_tag:create',
                'member_tag:delete',
                'tag:create',
                'custom_field:update',
                'api_proxy_member-group-registration',
            ],
            dependencies: ['member.viewer'],
        },
        creator: {
            privileges: ['member:create'],
            dependencies: [
                'member.viewer',
                'member.editor',
            ],
        },
        deleter: {
            privileges: ['member:delete'],
            dependencies: ['member.viewer'],
        },
    },
});
