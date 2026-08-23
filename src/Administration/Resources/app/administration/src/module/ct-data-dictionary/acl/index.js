Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'data_dictionary',
    roles: {
        viewer: {
            privileges: [
                'data_dictionary:read',
                'data_dictionary_item:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'data_dictionary:update',
                'data_dictionary_item:update',
            ],
            dependencies: ['data_dictionary.viewer'],
        },
        creator: {
            privileges: [
                'data_dictionary:create',
                'data_dictionary_item:create',
            ],
            dependencies: [
                'data_dictionary.viewer',
                'data_dictionary.editor',
            ],
        },
        deleter: {
            privileges: [
                'data_dictionary:delete',
                'data_dictionary_item:delete',
            ],
            dependencies: ['data_dictionary.viewer'],
        },
    },
});
