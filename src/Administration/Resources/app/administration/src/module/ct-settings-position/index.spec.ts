/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type PrivilegesService from 'src/app/service/privileges.service';

describe('src/module/ct-settings-position', () => {
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

    it('registers Position as an independent General settings module', () => {
        expect(Contena.Component.getComponentRegistry().has('ct-settings-position-list')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-settings-position-detail')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('ct-settings-position-create')).toBe(true);
        expect(Contena.Component.getComponentRegistry().has('mt-position-form')).toBe(true);

        const module = Contena.Module.getModuleRegistry().get('ct-settings-position');
        expect(module?.manifest.settingsItem).toContainEqual(
            expect.objectContaining({
                group: 'general',
                to: 'ct.settings.position.index',
                icon: 'regular-medal',
                privilege: 'position.viewer',
            }),
        );
    });

    it('protects the Position route with Position privileges', () => {
        const module = Contena.Module.getModuleRegistry().get('ct-settings-position');

        expect(module?.routes.get('ct.settings.position.index')).toMatchObject({
            meta: { privilege: 'position.viewer' },
        });
        expect(module?.routes.get('ct.settings.position.detail')).toMatchObject({
            meta: { privilege: 'position.viewer' },
        });
        expect(module?.routes.get('ct.settings.position.create')).toMatchObject({
            meta: { privilege: 'position.creator' },
        });
    });
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */
