Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'rule',
    roles: {
        viewer: {
            privileges: [
                'rule:read',
                'rule_condition:read',
                'language:read',
                'flow:read',
                'flow_sequence:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'rule:update',
                'rule_condition:create',
                'rule_condition:update',
                'rule_condition:delete',
            ],
            dependencies: ['rule.viewer'],
        },
        creator: {
            privileges: ['rule:create'],
            dependencies: [
                'rule.viewer',
                'rule.editor',
            ],
        },
        deleter: {
            privileges: ['rule:delete'],
            dependencies: ['rule.viewer'],
        },
    },
});
