/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-settings-region', () => {
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

    it('registers Region as an independent settings module', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-settings-region-list')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-region-tree')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-region-form')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-settings-region');
        expect(module?.manifest.settingsItem).toContainEqual(
            expect.objectContaining({
                group: 'localization',
                to: 'ct.settings.region.index',
                privilege: 'region.viewer',
            }),
        );
    });

    it('protects the Region route with Region privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-settings-region');

        expect(module?.routes.get('ct.settings.region.index')).toMatchObject({
            meta: { privilege: 'region.viewer' },
        });
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
