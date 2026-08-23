Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'flow',
    roles: {
        viewer: {
            privileges: [
                'flow:read',
                'flow_sequence:read',
                'flow_template:read',
                'rule:read',
                'mail_template:read',
                'mail_template_type:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'flow:update',
                'flow_sequence:create',
                'flow_sequence:update',
                'flow_sequence:delete',
            ],
            dependencies: ['flow.viewer'],
        },
        creator: {
            privileges: ['flow:create'],
            dependencies: [
                'flow.viewer',
                'flow.editor',
            ],
        },
        deleter: {
            privileges: ['flow:delete'],
            dependencies: ['flow.viewer'],
        },
    },
});
