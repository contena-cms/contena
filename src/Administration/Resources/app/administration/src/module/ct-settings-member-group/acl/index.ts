Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'member',
    key: 'member_groups',
    roles: {
        viewer: {
            privileges: [
                'member_group:read',
                'member_group_translation:read',
                'member_group_registration_channel:read',
                'channel:read',
                'channel_domain:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'member_group:update',
                'member_group_translation:create',
                'member_group_translation:update',
                'member_group_registration_channel:create',
                'member_group_registration_channel:delete',
                'custom_field:update',
            ],
            dependencies: ['member_groups.viewer'],
        },
        creator: {
            privileges: ['member_group:create'],
            dependencies: [
                'member_groups.viewer',
                'member_groups.editor',
            ],
        },
        deleter: { privileges: ['member_group:delete'], dependencies: ['member_groups.viewer'] },
    },
});
