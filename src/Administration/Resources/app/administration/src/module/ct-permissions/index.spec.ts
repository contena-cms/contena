import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-permissions', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                }) as unknown as PrivilegesService,
        );
        await import('./index');
    });

    it('registers the menu-oriented role access pages', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-permissions-role-access')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-permissions-role-form-modal')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-permissions-role-permissions-modal')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-permissions-role-view-additional')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-permissions-security')).toBe(false);
        expect(Contena.Component.getComponentRegistry().has('ct-permissions-configuration')).toBe(false);

        const module = Contena.Module.getModuleRegistry().get('ct-permissions');

        expect(module?.routes.get('ct.permissions.security')).toBeUndefined();
        expect(module?.routes.get('ct.permissions.role.detail.additional-permissions')).toMatchObject({
            component: 'ct-permissions-role-view-additional',
            meta: {
                privilege: 'users_and_permissions.viewer',
            },
        });
    });

    it('does not expose administrator security as a system settings item', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-permissions');

        expect(module?.manifest.settingsItem).toBeUndefined();
    });
});
