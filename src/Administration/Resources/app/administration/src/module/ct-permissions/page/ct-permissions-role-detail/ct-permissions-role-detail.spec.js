/* eslint-disable ct-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import PrivilegesService from 'src/app/service/privileges.service';

let privilegesService = new PrivilegesService();

function isNew() {
    return false;
}

async function createWrapper(
    { privileges = [], privilegeMappingEntries = [], aclPrivileges = [] } = {},
    options = {
        isNew: false,
    },
    roleSaveFunction = jest.fn(() => Promise.resolve()),
) {
    privilegeMappingEntries.forEach((mappingEntry) => privilegesService.addPrivilegeMappingEntry(mappingEntry));

    const route = {
        name: 'ct.permissions.role.detail.general',
        params: options.isNew ? {} : { id: '12345789' },
        query: {},
        meta: { $module: { title: 'ct-permissions.general.mainMenuItemGeneral' } },
    };

    return mount(
        await wrapTestComponent('ct-permissions-role-detail', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'ct-page': {
                        template: `
<div>
    <slot name="smart-bar-header"></slot>
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>
    `,
                    },
                    'ct-button-process': await wrapTestComponent('ct-button-process'),
                    'ct-card-view': {
                        template: '<div class="ct-card-view"><slot></slot></div>',
                    },
                    'ct-field': true,
                    'ct-permissions-permissions-grid': true,
                    'ct-permissions-additional-permissions': true,
                    'mt-tabs': true,
                    'router-view': true,
                    'ct-skeleton': true,
                    'ct-loader': true,
                },
                provide: {
                    [routeLocationKey]: route,
                    [routerKey]: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return aclPrivileges.includes(identifier);
                        },
                    },
                    repositoryFactory: {
                        create: () => ({
                            create: () => ({
                                isNew: () => true,
                                name: '',
                            }),
                            get: () =>
                                Promise.resolve({
                                    isNew: isNew,
                                    name: 'demoRole',
                                    privileges: privileges,
                                }),
                            save: roleSaveFunction,
                        }),
                    },
                    userService: {},
                    privileges: privilegesService,
                },
            },
        },
    );
}

describe('module/ct-permissions/page/ct-permissions-role-detail', () => {
    let wrapper;

    beforeEach(async () => {
        privilegesService = new PrivilegesService();
    });

    it('separates the tab navigation from its content', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.ct-permissions-role-detail__tab-content').exists()).toBe(true);
    });

    it('should not contain any privileges', async () => {
        wrapper = await createWrapper({
            privileges: [
                'system:clear:cache',
                'system.clear_cache',
            ],
        });

        await flushPromises();

        expect(wrapper.vm.role.privileges).toHaveLength(0);
    });

    it('should contain only role privileges', async () => {
        wrapper = await createWrapper({
            privileges: [
                'system:clear:cache',
                'system.clear_cache',
            ],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(wrapper.vm.role.privileges).not.toContain('system:clear:cache');
    });

    it('should contain only roles privileges', async () => {
        wrapper = await createWrapper({
            privileges: [
                'workflows.execute',
                'system.clear_cache',
            ],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'workflows',
                    roles: {
                        execute: {
                            privileges: ['workflow:execute'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(wrapper.vm.role.privileges).toContain('workflows.execute');
        expect(wrapper.vm.role.privileges).not.toContain('system:clear:cache');
        expect(wrapper.vm.role.privileges).not.toContain('workflow:execute');
    });

    it('should filter custom privileges', async () => {
        wrapper = await createWrapper({
            privileges: [
                'workflows.execute',
                'system.clear_cache',
                'language:read',
                'country:read',
                'media:update',
                'workflow:read',
            ],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'workflows',
                    roles: {
                        execute: {
                            privileges: ['workflow:execute'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(wrapper.vm.role.privileges).toContain('workflows.execute');
        expect(wrapper.vm.role.privileges).not.toContain('system:clear:cache');
        expect(wrapper.vm.role.privileges).not.toContain('workflow:execute');
        expect(wrapper.vm.role.privileges).not.toContain('language:read');
        expect(wrapper.vm.role.privileges).not.toContain('country:read');
        expect(wrapper.vm.role.privileges).not.toContain('media:update');
        expect(wrapper.vm.role.privileges).not.toContain('workflow:read');

        expect(wrapper.vm.detailedPrivileges).toEqual([
            'media:update',
            'workflow:read',
        ]);
    });

    it('should save privilege with all privileges and admin privilege key combination', async () => {
        wrapper = await createWrapper({
            privileges: ['system.clear_cache'],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                ].sort(),
            },
            contextMock,
        );
    });

    it('should save privileges with all privileges and admin privilege key combinations', async () => {
        wrapper = await createWrapper({
            privileges: [
                'system.clear_cache',
                'workflows.execute',
            ],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'workflows',
                    roles: {
                        execute: {
                            privileges: ['workflow:execute'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    'workflows.execute',
                    'workflow:execute',
                ].sort(),
            },
            contextMock,
        );
    });

    it('should save privileges with all privileges, admin privilege key combinations and detailed privileges', async () => {
        wrapper = await createWrapper({
            privileges: [
                'system.clear_cache',
                'workflows.execute',
                'media:read',
            ],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'workflows',
                    roles: {
                        execute: {
                            privileges: ['workflow:execute'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    'workflows.execute',
                    'workflow:execute',
                    'media:read',
                ].sort(),
            },
            contextMock,
        );
    });

    it('should merge privileges and detailed privileges', async () => {
        wrapper = await createWrapper({
            privileges: [
                'system.clear_cache',
                'workflows.execute',
                'media:read',
            ],
            privilegeMappingEntries: [
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'system',
                    roles: {
                        clear_cache: {
                            privileges: ['system:clear:cache'],
                            dependencies: [],
                        },
                    },
                },
                {
                    category: 'additional_permissions',
                    parent: null,
                    key: 'workflows',
                    roles: {
                        execute: {
                            privileges: ['workflow:execute'],
                            dependencies: [],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        wrapper.vm.detailedPrivileges.push('country:update');

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
                    'system.clear_cache',
                    'system:clear:cache',
                    'workflows.execute',
                    'workflow:execute',
                    'media:read',
                    'country:update',
                ].sort(),
            },
            contextMock,
        );
    });

    it('should save privileges with all privileges from getPrivileges() method', async () => {
        wrapper = await createWrapper({
            privileges: [
                'promotion.viewer',
                'promotion.editor',
                'promotion.creator',
            ],
            privilegeMappingEntries: [
                {
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
                            dependencies: [
                                'rule.viewer',
                            ],
                        },
                        creator: {
                            privileges: ['rule:create'],
                            dependencies: [
                                'rule.viewer',
                                'rule.editor',
                            ],
                        },
                    },
                },
                {
                    category: 'permissions',
                    parent: null,
                    key: 'promotion',
                    roles: {
                        viewer: {
                            privileges: ['promotion:read'],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'promotion:update',
                            ],
                            dependencies: [
                                'promotion.viewer',
                            ],
                        },
                        creator: {
                            privileges: [
                                'promotion:create',
                                privilegesService.getPrivileges('rule.creator'),
                            ],
                            dependencies: [
                                'promotion.viewer',
                                'promotion.editor',
                            ],
                        },
                    },
                },
            ],
        });

        await flushPromises();

        expect(wrapper.vm.roleRepository.save).not.toHaveBeenCalled();

        const contextMock = { access: '1a2b3c' };
        wrapper.vm.saveRole(contextMock);

        expect(wrapper.vm.roleRepository.save).toHaveBeenCalledWith(
            {
                isNew: isNew,
                name: 'demoRole',
                privileges: [
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
            },
            contextMock,
        );
    });

    it('should save directly from the save action', async () => {
        const saveFunction = jest.fn().mockResolvedValue(undefined);
        wrapper = await createWrapper(
            {
                aclPrivileges: ['users_and_permissions.editor'],
            },
            { isNew: false },
            saveFunction,
        );
        wrapper.vm.isLoading = false;
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-permissions-role-detail__button-save');
        await saveButton.trigger('click.prevent');
        await flushPromises();

        expect(saveFunction).toHaveBeenCalledWith(expect.any(Object), Contena.Context.api);
    });

    it('should show the name of the role as the title', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const title = wrapper.find('h2');
        expect(title.text()).toBe('demoRole');
    });

    it('should not show the create new snippet when user deletes name', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const title = wrapper.find('h2');
        expect(title.text()).toBe('demoRole');

        wrapper.vm.role.name = '';
        await flushPromises();

        expect(title.text()).toBe('');
    });

    it('should show the create new role snippet as the title', async () => {
        wrapper = await createWrapper(
            {},
            {
                isNew: true,
            },
        );
        wrapper.vm.isLoading = false;
        await wrapper.vm.$nextTick();

        const title = wrapper.find('h2');
        expect(title.text()).toBe('ct-permissions.roles.general.labelCreateNewRole');
    });

    it('should replace the create new role snippet as the title when user types name', async () => {
        wrapper = await createWrapper(
            {},
            {
                isNew: true,
            },
        );
        wrapper.vm.isLoading = false;
        await wrapper.vm.$nextTick();
        await flushPromises();

        let title = wrapper.find('h2');
        expect(title.text()).toBe('ct-permissions.roles.general.labelCreateNewRole');

        wrapper.vm.role = {
            ...wrapper.vm.role,
            name: 'Test',
        };
        await wrapper.vm.$nextTick();

        await flushPromises();

        title = wrapper.find('h2');
        expect(title.text()).toBe('Test');
    });

    it('should disable the button and fields when no aclPrivileges exists', async () => {
        wrapper = await createWrapper({
            aclPrivileges: [],
        });
        wrapper.vm.isLoading = false;
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-permissions-role-detail__button-save');
        expect(saveButton.attributes().disabled).toBeDefined();
    });

    it('should enable the button and fields when edit aclPrivileges exists', async () => {
        wrapper = await createWrapper({
            aclPrivileges: ['users_and_permissions.editor'],
        });
        wrapper.vm.isLoading = false;
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-permissions-role-detail__button-save');
        expect(saveButton.attributes().disabled).toBeUndefined();
    });
});
