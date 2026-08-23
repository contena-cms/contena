import { shallowMount } from '@vue/test-utils';

describe('module/ct-permissions/view/ct-permissions-role-view-additional', () => {
    it('exports a native SFC entrypoint for lazy route loading', async () => {
        const { default: component } = await import('./index');

        expect(component._renderedBySfcTemplate).toBe(true);
    });

    it('renders additional permissions outside the advanced view', async () => {
        const role = { privileges: ['system.system_config'] };
        const detailedPrivileges = ['api_action_cache_index'];
        const wrapper = shallowMount(
            await wrapTestComponent('ct-permissions-role-view-additional', {
                sync: true,
            }),
            {
                props: { role, detailedPrivileges },
                global: {
                    provide: {
                        acl: { can: () => true },
                    },
                    stubs: {
                        'ct-block': { template: '<div><slot /></div>' },
                        'mt-banner': { template: '<div><slot /></div>' },
                        'ct-permissions-additional-permissions': {
                            name: 'ct-permissions-additional-permissions',
                            props: ['role'],
                            template: '<div />',
                        },
                        'ct-permissions-detailed-additional-permissions': {
                            name: 'ct-permissions-detailed-additional-permissions',
                            props: [
                                'role',
                                'detailedPrivileges',
                            ],
                            template: '<div />',
                        },
                    },
                },
            },
        );

        const permissions = wrapper.findComponent({ name: 'ct-permissions-additional-permissions' });
        const routePermissions = wrapper.findComponent({ name: 'ct-permissions-detailed-additional-permissions' });

        expect(permissions.exists()).toBe(true);
        expect(permissions.props('role')).toStrictEqual(role);
        expect(routePermissions.exists()).toBe(true);
        expect(routePermissions.props('role')).toStrictEqual(role);
        expect(routePermissions.props('detailedPrivileges')).toStrictEqual(detailedPrivileges);
    });
});
