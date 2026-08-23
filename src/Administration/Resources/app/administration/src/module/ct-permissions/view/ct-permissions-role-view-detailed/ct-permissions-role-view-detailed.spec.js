import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('ct-permissions-role-view-detailed', {
            sync: true,
        }),
        {
            props: {
                role: {},
                detailedPrivileges: [],
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'ct-permissions-detailed-permissions-grid': true,
                    'ct-permissions-detailed-additional-permissions': true,
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                },
            },
        },
    );
}

describe('module/ct-permissions/view/ct-permissions-role-view-detailed', () => {
    it('should disable the detailed permission grid when no aclPrivileges exists', async () => {
        const wrapper = await createWrapper();

        const detailedPermissionGrid = wrapper.find('ct-permissions-detailed-permissions-grid-stub');

        expect(detailedPermissionGrid.attributes().disabled).toBe('true');
    });

    it('should enable the detailed permission grid when edit aclPrivileges exists', async () => {
        const wrapper = await createWrapper(['users_and_permissions.editor']);

        const detailedPermissionGrid = wrapper.find('ct-permissions-detailed-permissions-grid-stub');

        expect(detailedPermissionGrid.attributes().disabled).toBeUndefined();
    });

    it('does not show mapped system actions in the advanced view', async () => {
        const wrapper = await createWrapper();

        const additionalPermissions = wrapper.find('ct-permissions-additional-permissions-stub');
        const routePermissions = wrapper.find('ct-permissions-detailed-additional-permissions-stub');

        expect(additionalPermissions.exists()).toBe(false);
        expect(routePermissions.exists()).toBe(false);
    });

    it('should show an alert which contains the help text', async () => {
        const wrapper = await createWrapper();

        const alert = wrapper.find('[role="banner"]');
        expect(alert.text()).toBe('ct-permissions.roles.view.detailed.alertText');
    });
});
