/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-settings-organization', () => {
    beforeAll(async () => {
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                }) as unknown as PrivilegesService,
        );
        await import('./index');
    });

    it('registers Organization as an independent General settings module', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-settings-organization-list')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('mt-organization-tree')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('mt-organization-form')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-settings-organization');
        expect(module?.manifest.settingsItem).toContainEqual(
            expect.objectContaining({
                group: 'general',
                to: 'ct.settings.organization.index',
                privilege: 'organization.viewer',
            }),
        );
    });

    it('protects the Organization route with Organization privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-settings-organization');

        expect(module?.routes.get('ct.settings.organization.index')).toMatchObject({
            meta: { privilege: 'organization.viewer' },
        });
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
