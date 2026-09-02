import { mount } from '@vue/test-utils';

describe('module/ct-permissions/component/ct-permissions-additional-permissions', () => {
    /**
     * @type VueWrapper
     */
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(
            await wrapTestComponent('ct-permissions-additional-permissions', {
                sync: true,
            }),
            {
                props: {
                    role: {
                        privileges: [],
                    },
                },
                attachTo: document.body,
                global: {
                    renderStubDefaultSlot: true,
                    stubs: {
                        'ct-base-field': true,
                        'ct-field-error': true,
                    },
                    provide: {
                        acl: {
                            can: () => true,
                        },
                        privileges: {
                            getPrivilegesMappings: () => [
                                {
                                    category: 'additional_permissions',
                                    key: 'system',
                                    parent: null,
                                    roles: {
                                        clear_cache: {
                                            dependencies: [],
                                            privileges: ['system:clear:cache'],
                                        },
                                        core_update: {
                                            dependencies: [],
                                            privileges: ['system:core:update'],
                                        },
                                        plugin_maintain: {
                                            dependencies: [],
                                            privileges: [
                                                'system:plugin:maintain',
                                            ],
                                        },
                                    },
                                },
                                {
                                    category: 'additional_permissions',
                                    key: 'workflows',
                                    parent: null,
                                    roles: {
                                        execute: {
                                            dependencies: [],
                                            privileges: [
                                                'workflow:execute',
                                            ],
                                        },
                                    },
                                },
                                {
                                    category: 'permissions',
                                    key: 'media',
                                    parent: null,
                                    roles: {
                                        viewer: {
                                            dependencies: [],
                                            privileges: [],
                                        },
                                        editor: {
                                            dependencies: [],
                                            privileges: [],
                                        },
                                        creator: {
                                            dependencies: [],
                                            privileges: [],
                                        },
                                        deleter: {
                                            dependencies: [],
                                            privileges: [],
                                        },
                                    },
                                },
                            ],
                        },
                    },
                },
            },
        );

        await flushPromises();
    });

    it('should display all keys from the category additional_permissions', async () => {
        const systemPermissions = wrapper.find('.ct-permissions-additional-permissions_system');
        const workflowsPermissions = wrapper.find('.ct-permissions-additional-permissions_workflows');

        expect(systemPermissions.exists()).toBeTruthy();
        expect(workflowsPermissions.exists()).toBeTruthy();
    });

    it('should not display keys from other categories', async () => {
        const mediaPermissions = wrapper.find('.ct-permissions-additional-permissions_media');

        expect(mediaPermissions.exists()).toBeFalsy();
    });

    it('should show all roles after the key', async () => {
        const fields = wrapper.findAllComponents({ name: 'MtSwitch' });
        const labels = fields.map((field) => field.props().label);

        expect(labels).toEqual([
            'ct-privileges.additional_permissions.system.clear_cache',
            'ct-privileges.additional_permissions.system.core_update',
            'ct-privileges.additional_permissions.system.plugin_maintain',
            'ct-privileges.additional_permissions.workflows.execute',
        ]);
    });

    it('should contain the a true value in a field when the privilege is in roles', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache'],
            },
        });

        await flushPromises();

        const clearCacheField = wrapper.findAllComponents('.mt-switch').find((field) => {
            return field.classes().includes('ct_permissions_additional_permissions_system_clear_cache');
        });
        expect(clearCacheField.props().modelValue).toBeTruthy();
    });

    it('should contain the a false value in a field when the privilege is not in roles', async () => {
        const clearCacheField = wrapper.findComponent('.ct_permissions_additional_permissions_system_clear_cache');

        expect(clearCacheField.props().modelValue).toBeFalsy();
    });

    it('should add the checked value to the role privileges', async () => {
        const clearCacheField = wrapper.findAllComponents('.mt-switch').find((field) => {
            return field.classes().includes('ct_permissions_additional_permissions_system_clear_cache');
        });

        expect(clearCacheField.props().modelValue).toBeFalsy();

        await clearCacheField.find('input').setChecked(true);
        await flushPromises();

        expect(wrapper.vm.role.privileges).toContain('system.clear_cache');
        expect(clearCacheField.props().modelValue).toBeTruthy();
    });

    it('should remove the value when it get unchecked', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache'],
            },
        });

        const clearCacheField = wrapper.findAllComponents('.mt-switch').find((field) => {
            return field.classes().includes('ct_permissions_additional_permissions_system_clear_cache');
        });

        expect(clearCacheField.props().modelValue).toBeTruthy();

        await clearCacheField.find('input').setChecked(false);
        await flushPromises();
        await clearCacheField.trigger('click');
        await wrapper.vm.$forceUpdate();

        expect(wrapper.vm.role.privileges).not.toContain('system.clear_cache');
        expect(clearCacheField.props().modelValue).toBeFalsy();
    });

    it('should not add privileges which the current user cannot grant', async () => {
        wrapper = mount(
            await wrapTestComponent('ct-permissions-additional-permissions', {
                sync: true,
            }),
            {
                props: {
                    role: {
                        privileges: [],
                    },
                },
                global: {
                    provide: {
                        acl: {
                            can: () => false,
                        },
                        privileges: {
                            getPrivilegesMappings: () => [
                                {
                                    category: 'additional_permissions',
                                    key: 'system',
                                    parent: null,
                                    roles: {
                                        clear_cache: {
                                            dependencies: [],
                                            privileges: ['system:clear:cache'],
                                        },
                                    },
                                },
                            ],
                        },
                    },
                },
            },
        );

        wrapper.vm.onSelectPrivilege('system.clear_cache', true);

        expect(wrapper.vm.role.privileges).toEqual([]);
    });

    it('should disable all checkboxes', async () => {
        await wrapper.setProps({
            role: {
                privileges: ['system.clear_cache'],
            },
            disabled: true,
        });
        await flushPromises();

        wrapper.findAll('.ct-field--switch').forEach((field) => {
            expect(field.classes()).toContain('is--disabled');
        });
    });
});
