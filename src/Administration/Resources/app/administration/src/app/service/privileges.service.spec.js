/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */

import PrivilegesService from 'src/app/service/privileges.service';

const defaultUserPrivileges = [
    'language:read',
    'locale:read',
    'message_queue_stats:read',
    'log_entry:create',
    'currency:read',
    'country:read',
    'scheduled_task:read',
];

describe('src/app/service/privileges.service.js', () => {
    beforeEach(async () => {
        global.console.warn = jest.fn();
        global.console.error = jest.fn();
    });

    afterEach(() => {
        global.console.warn.mockReset();
        global.console.error.mockReset();
    });

    it('should contain no privilege mappings', async () => {
        const privilegesService = new PrivilegesService();

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should expose the default admin_user privileges for role UI display', async () => {
        const privilegesService = new PrivilegesService();

        expect(privilegesService.getDefaultUserPrivileges()).toStrictEqual(defaultUserPrivileges);
    });

    it('should add a privilege mapping', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMapping);

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(1);
        expect(privilegesService.getPrivilegesMappings()[0]).toStrictEqual(privilegeMapping);
    });

    it('should add a list of privilege mappings', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappings = [
            {
                category: 'permissions',
                parent: null,
                key: 'foo',
                roles: {},
            },
            {
                category: 'permissions',
                parent: null,
                key: 'bar',
                roles: {},
            },
        ];

        privilegesService.addPrivilegeMappingEntries(privilegeMappings);

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(2);
        expect(privilegesService.getPrivilegesMappings()[0]).toStrictEqual(privilegeMappings[0]);
        expect(privilegesService.getPrivilegesMappings()[1]).toStrictEqual(privilegeMappings[1]);
    });

    it('should throw a warning if the argument is not an object', async () => {
        const privilegesService = new PrivilegesService();

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry('notAnObject');
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping has to be an object.',
        );

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should throw an error if the argument is not an array', async () => {
        const privilegesService = new PrivilegesService();

        expect(global.console.error).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntries('notAnArray');
        expect(global.console.error).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntries]',
            'The privilegeMappings must be an array.',
        );

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should throw a warning if property mapping is null', async () => {
        const privilegesService = new PrivilegesService();

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(null);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping must not be null.',
        );

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should throw a warning if the property category is missing', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(privilegeMapping);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping need the property "category".',
        );

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should throw a warning if the property parent is missing', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            category: 'additional_permissions',
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(privilegeMapping);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping need the property "parent".',
        );

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should throw a warning if the property key is missing', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMapping = {
            category: 'additional_permissions',
            parent: null,
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        expect(global.console.warn).not.toHaveBeenCalled();
        privilegesService.addPrivilegeMappingEntry(privilegeMapping);
        expect(global.console.warn).toHaveBeenCalledWith(
            '[addPrivilegeMappingEntry]',
            'The privilegeMapping need the property "key".',
        );

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(0);
    });

    it('should add multiple privilege mappings', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'orders',
            roles: {
                create_discounts: {
                    privileges: ['order:create:discount'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.getPrivilegesMappings()).toHaveLength(2);
        expect(privilegesService.getPrivilegesMappings()[0]).toStrictEqual(privilegeMappingOne);
        expect(privilegesService.getPrivilegesMappings()[1]).toStrictEqual(privilegeMappingTwo);
    });

    it('should merge multiple privileges with same category and key', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        const privilegeMappings = privilegesService.getPrivilegesMappings();
        expect(privilegeMappings).toHaveLength(1);

        expect(privilegeMappings[0].roles).toMatchObject({
            clear_cache: {
                privileges: ['system:clear:cache'],
                dependencies: [],
            },
            core_update: {
                privileges: ['system:core:update'],
                dependencies: [],
            },
        });
    });

    it('should return the privilege with all roles', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService._getPrivilege('system.core_update')).toMatchObject({
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },

                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        });
    });

    it('should return the exact privilege role', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.getPrivilegeRole('system.core_update')).toMatchObject({
            privileges: ['system:core:update'],
            dependencies: [],
        });

        expect(privilegesService.getPrivilegeRole('system.clear_cache')).toMatchObject({
            privileges: ['system:clear:cache'],
            dependencies: [],
        });
    });

    it('should return undefined when the exact privilege role does not exist', async () => {
        const privilegesService = new PrivilegesService();

        expect(privilegesService.getPrivilegeRole('does.not_exists')).toBeUndefined();
    });

    it('should check if the privilege exists', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        expect(privilegesService.existsPrivilege('system.core_update')).toBeTruthy();
        expect(privilegesService.existsPrivilege('system.not_exists')).toBeFalsy();
    });

    it('should filter only matching privileges', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        const testPrivileges = ['system.clear_cache', 'system:clear:cache', 'orders:read'];

        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).toContain('system.clear_cache');
        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).not.toContain('system:clear:cache.');
        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).not.toContain('orders:read.');
    });

    it('should filter only matching privileges and duplicates', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingOne = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                clear_cache: {
                    privileges: ['system:clear:cache'],
                    dependencies: [],
                },
            },
        };

        const privilegeMappingTwo = {
            category: 'additional_permissions',
            parent: null,
            key: 'system',
            roles: {
                core_update: {
                    privileges: ['system:core:update'],
                    dependencies: [],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingOne);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingTwo);

        const testPrivileges = ['system.clear_cache', 'system.clear_cache'];

        expect(privilegesService.filterPrivilegesRoles(testPrivileges)).toStrictEqual(['system.clear_cache']);
    });

    it('should return the function getPrivilegesWithDependencies without adding admin identifier', async () => {
        const privilegesService = new PrivilegesService();
        privilegesService._getPrivilegesWithDependencies = jest.fn();

        const returnFunction = privilegesService.getPrivileges('blog.editor');
        expect(privilegesService._getPrivilegesWithDependencies).not.toHaveBeenCalled();

        expect(typeof returnFunction).toBe('function');
        returnFunction();

        expect(privilegesService._getPrivilegesWithDependencies).toHaveBeenCalledWith('blog.editor', false);
    });

    it('should return all privileges for a direct role with dependencies', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingRule = {
            category: 'permissions',
            parent: null,
            key: 'rule',
            roles: {
                viewer: {
                    privileges: ['rule:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['rule:update'],
                    dependencies: ['rule.viewer'],
                },
                creator: {
                    privileges: ['rule:create'],
                    dependencies: ['rule.viewer', 'rule.editor'],
                },
            },
        };

        const privilegeMappingPromotion = {
            category: 'permissions',
            parent: null,
            key: 'promotion',
            roles: {
                viewer: {
                    privileges: ['promotion:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['promotion:update'],
                    dependencies: ['promotion.viewer'],
                },
                creator: {
                    privileges: ['promotion:create', privilegesService.getPrivileges('rule.creator')],
                    dependencies: ['promotion.viewer', 'promotion.editor'],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingRule);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingPromotion);

        const allPrivilegesWithDependencies = privilegesService.getPrivilegesForAdminPrivilegeKeys(['rule.editor']);
        expect(allPrivilegesWithDependencies).toStrictEqual(
            [
                'rule.editor',
                'rule.viewer',
                'rule:read',
                'rule:update',
            ].sort(),
        );
    });

    it('should return all privileges with dependencies', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingRule = {
            category: 'permissions',
            parent: null,
            key: 'rule',
            roles: {
                viewer: {
                    privileges: ['rule:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['rule:update'],
                    dependencies: ['rule.viewer'],
                },
                creator: {
                    privileges: ['rule:create'],
                    dependencies: ['rule.viewer', 'rule.editor'],
                },
            },
        };

        const privilegeMappingPromotion = {
            category: 'permissions',
            parent: null,
            key: 'promotion',
            roles: {
                viewer: {
                    privileges: ['promotion:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['promotion:update'],
                    dependencies: ['promotion.viewer'],
                },
                creator: {
                    privileges: ['promotion:create', privilegesService.getPrivileges('rule.creator')],
                    dependencies: ['promotion.viewer', 'promotion.editor'],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingRule);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingPromotion);

        const allPrivilegesWithDependencies = privilegesService.getPrivilegesForAdminPrivilegeKeys(['promotion.creator']);
        expect(allPrivilegesWithDependencies).toStrictEqual(
            [
                'promotion.viewer',
                'promotion:read',
                'promotion.editor',
                'promotion:update',
                'promotion.creator',
                'promotion:create',
                'rule:create',
                'rule:read',
                'rule:update',
            ].sort(),
        );
    });

    it('should only return default admin_user privileges when they are explicitly mapped to role assignments', async () => {
        const privilegesService = new PrivilegesService();

        privilegesService.addPrivilegeMappingEntry({
            category: 'permissions',
            parent: null,
            key: 'blog',
            roles: {
                viewer: {
                    privileges: ['blog:read', 'currency:read', 'country:read'],
                    dependencies: [],
                },
            },
        });

        const allPrivilegesWithDependencies = privilegesService.getPrivilegesForAdminPrivilegeKeys(['blog.viewer']);

        expect(allPrivilegesWithDependencies).toStrictEqual(
            [
                'blog.viewer',
                'blog:read',
                'currency:read',
                'country:read',
            ].sort(),
        );
    });

    it('should not call duplicated getPrivileges again', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingProduct = {
            category: 'permissions',
            parent: null,
            key: 'blog',
            roles: {
                viewer: {
                    privileges: ['blog:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['blog:update'],
                    dependencies: ['blog.viewer'],
                },
                creator: {
                    privileges: ['blog:create', privilegesService.getPrivileges('promotion.creator')],
                    dependencies: ['blog.viewer', 'blog.editor'],
                },
            },
        };

        const privilegeMappingRule = {
            category: 'permissions',
            parent: null,
            key: 'rule',
            roles: {
                viewer: {
                    privileges: ['rule:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['rule:update', privilegesService.getPrivileges('blog.creator')],
                    dependencies: ['rule.viewer'],
                },
                creator: {
                    privileges: ['rule:create'],
                    dependencies: ['rule.viewer', 'rule.editor'],
                },
            },
        };

        const privilegeMappingPromotion = {
            category: 'permissions',
            parent: null,
            key: 'promotion',
            roles: {
                viewer: {
                    privileges: ['promotion:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['promotion:update'],
                    dependencies: ['promotion.viewer'],
                },
                creator: {
                    privileges: ['promotion:create', privilegesService.getPrivileges('rule.creator')],
                    dependencies: ['promotion.viewer', 'promotion.editor'],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingProduct);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingRule);
        privilegesService.addPrivilegeMappingEntry(privilegeMappingPromotion);

        const allPrivilegesWithDependencies = privilegesService.getPrivilegesForAdminPrivilegeKeys(['promotion.creator']);
        expect(allPrivilegesWithDependencies).toStrictEqual(
            [
                'promotion.viewer',
                'promotion:read',
                'promotion.editor',
                'promotion:update',
                'promotion.creator',
                'promotion:create',
                'blog:read',
                'blog:update',
                'blog:create',
                'rule:create',
                'rule:read',
                'rule:update',
            ].sort(),
        );
    });

    it('should merge existing roles', async () => {
        const privilegesService = new PrivilegesService();

        const privilegeMappingCore = {
            category: 'permissions',
            parent: null,
            key: 'blog',
            roles: {
                viewer: {
                    privileges: ['blog:read'],
                    dependencies: [],
                },
                editor: {
                    privileges: ['blog:update'],
                    dependencies: ['blog.viewer'],
                },
                creator: {
                    privileges: ['blog:create'],
                    dependencies: ['blog.viewer', 'blog.editor'],
                },
            },
        };

        const privilegeMappingPlugin = {
            category: 'permissions',
            parent: null,
            key: 'blog',
            roles: {
                viewer: {
                    privileges: ['plugin:read'],
                },
                editor: {
                    privileges: ['plugin:update'],
                },
                newrole: {
                    privileges: ['plugin:write'],
                },
            },
        };

        privilegesService.addPrivilegeMappingEntry(privilegeMappingCore);

        let allPrivilegesWithDependencies = privilegesService.getPrivilegesForAdminPrivilegeKeys(['blog.editor']);
        expect(allPrivilegesWithDependencies).toStrictEqual(
            [
                'blog.editor',
                'blog.viewer',
                'blog:read',
                'blog:update',
            ].sort(),
        );

        privilegesService.addPrivilegeMappingEntry(privilegeMappingPlugin);

        allPrivilegesWithDependencies = privilegesService.getPrivilegesForAdminPrivilegeKeys(['blog.editor']);
        expect(allPrivilegesWithDependencies).toStrictEqual(
            [
                'plugin:update',
                'plugin:read',
                'blog.editor',
                'blog.viewer',
                'blog:read',
                'blog:update',
            ].sort(),
        );
    });
});
